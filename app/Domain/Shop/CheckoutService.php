<?php
declare(strict_types=1);

namespace Tpb\Domain\Shop;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Config\ConfigMapper;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\Customer\CustomerRepo;
use Tpb\Domain\Legal\LegalDocRepo;
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Order\OrderSnapshot;
use Tpb\Domain\Payment\Gateway\GatewayFactory;
use Tpb\Domain\Pricing\PriceEngine;
use Tpb\Domain\Pricing\PricingException;
use Tpb\Domain\Pricing\PricingRepo;

/**
 * Shop-Checkout (Pfad B, §7). Der Preis wird IMMER serverseitig neu berechnet – der
 * Browser sendet nie einen Betrag. Erzeugt in EINER Transaktion die Order (PENDING_PAYMENT),
 * Positionen/Einheiten, Rechtserklärungen und die Zahlungsabsicht beim Gateway; gibt die
 * gehostete Checkout-URL zurück. Shop-Orders haben deposit_required_cents = 0.
 */
final class CheckoutService
{
    /** Pflicht-Zustimmungen (inkl. Widerruf-Erlöschen bei personalisierter Ware). */
    private const REQUIRED_DOCS = ['agb', 'widerruf'];

    /**
     * @param array<string,mixed> $customerData
     * @param array<int,string>   $acceptedDocTypes
     * @return array{order_public_id:string,checkout_url:string,intent_public_id:string,total_cents:int}
     */
    public static function start(string $configPublicId, array $customerData, array $acceptedDocTypes): array
    {
        $payload = ConfigurationRepo::loadByPublicId($configPublicId);
        if ($payload === null) {
            throw new CheckoutException('Konfiguration nicht gefunden.');
        }
        if (($payload['status'] ?? '') === 'ordered') {
            throw new CheckoutException('Diese Konfiguration wurde bereits bestellt.');
        }

        // Pflicht-Zustimmungen serverseitig prüfen (Client kann das nicht umgehen).
        $published = array_column(LegalDocRepo::allPublished('de'), 'doc_type');
        foreach (self::REQUIRED_DOCS as $docType) {
            if (in_array($docType, $published, true) && !in_array($docType, $acceptedDocTypes, true)) {
                throw new CheckoutException('Bitte die erforderlichen Erklärungen bestätigen (AGB und Widerrufsbelehrung).');
            }
        }

        $resolved = ConfigMapper::resolve($payload);
        $pb = PricingRepo::activePriceBook();
        $cv = PricingRepo::activeCostVersion();
        if ($pb === null || $cv === null) {
            throw new CheckoutException('Kein veröffentlichtes Preisbuch vorhanden.');
        }
        try {
            $breakdown = PriceEngine::calculate(ConfigMapper::toEngineConfig($resolved), $pb, $cv);
        } catch (PricingException $e) {
            throw new CheckoutException($e->getMessage());
        }
        if ($breakdown->belowMinOrder) {
            throw new CheckoutException('Der Mindestbestellwert ist nicht erreicht.');
        }

        return Db::tx(function () use ($configPublicId, $customerData, $acceptedDocTypes, $payload, $resolved, $breakdown, $pb): array {
            $now = Clock::nowUtcSeconds();
            $customer = CustomerRepo::upsert($customerData);
            $custRow = CustomerRepo::findById((int) $customer['id']);

            $snapshot = OrderSnapshot::build($payload, $resolved, $breakdown, $pb, $custRow);

            // Versandkosten nach Zone (national/international) + Gratis-Schwelle; in den Preis einkalkuliert.
            $shipping = \Tpb\Domain\Shipping\ShippingRates::costFor((string) ($customerData['billing_country'] ?? ''), $breakdown->totalCents);
            $shipCost = (int) $shipping['cost_cents'];
            $productTotal = (int) $snapshot['totals']['total_cents'];
            $snapshot['lines'][] = [
                'pos_no' => count($snapshot['lines']) + 1, 'sku' => null,
                'description' => 'Versand (' . (string) $shipping['carrier'] . ')' . ($shipping['free_applied'] ? ' – gratis' : ''),
                'qty' => 1, 'unit_cents' => $shipCost, 'line_cents' => $shipCost,
            ];
            $snapshot['totals']['shipping_cents'] = $shipCost;
            $snapshot['totals']['total_cents'] = $productTotal + $shipCost;

            $order = OrderRepo::createFromSnapshot((int) $customer['id'], null, $pb->currency, $snapshot, 'PENDING_PAYMENT', null, 'shop_checkout');
            $orderId = (int) $order['id'];
            $orderTotal = $productTotal + $shipCost;

            // Sendung mit Versandart/Carrier/Kosten anlegen (Adresse aus dem Kundenstamm).
            $shipmentRow = \Tpb\Domain\Shipping\ShipmentRepo::ensureForOrder($orderId, null);
            \Tpb\Domain\Shipping\ShipmentRepo::update((int) $shipmentRow['id'], [
                'method' => (string) $shipping['method'], 'carrier' => (string) $shipping['carrier'], 'shipping_cost_cents' => $shipCost,
            ]);

            // Bestand reservieren (kein Oversell). Abbuchung erst bei bezahlter Bestellung.
            $variantQtys = [];
            foreach ($resolved['items'] as $it) {
                foreach ($it['sizes'] as $s) {
                    $vid = (int) $s['variant_id'];
                    $variantQtys[$vid] = ($variantQtys[$vid] ?? 0) + (int) $s['qty'];
                }
            }
            try {
                \Tpb\Domain\Stock\StockService::reserveForOrder($orderId, $variantQtys, null);
            } catch (\Tpb\Domain\Stock\StockException $e) {
                throw new CheckoutException($e->getMessage());
            }

            // Rechtserklärungen festhalten.
            foreach (LegalDocRepo::allPublished('de') as $doc) {
                if (in_array((string) $doc['doc_type'], $acceptedDocTypes, true)) {
                    Db::run(
                        'INSERT INTO order_terms_acceptance (order_id, doc_type, legal_doc_version_id, shown_at, accepted_at, actor_label, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$orderId, (string) $doc['doc_type'], (int) $doc['id'], $now, $now, 'shop_customer', $now]
                    );
                }
            }

            $configId = (int) Db::run('SELECT id FROM configurations WHERE public_id = ? LIMIT 1', [$configPublicId])->fetchColumn();
            Db::run("UPDATE configurations SET customer_id = ?, guest_email = ?, status = 'ordered', updated_at = ? WHERE id = ?",
                [$customer['id'], $custRow['email'], $now, $configId]);

            // Zahlungsabsicht beim aktiven Gateway (Testmodus).
            $gateway = GatewayFactory::active();
            $intentPublic = Ulid::generate();
            $checkout = $gateway->createCheckout($intentPublic, $orderTotal, $pb->currency);
            Db::run(
                'INSERT INTO payment_intents (public_id, order_id, provider, provider_ref, amount_cents, currency, status, checkout_url, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$intentPublic, $orderId, $gateway->provider(), $checkout['provider_ref'], $orderTotal, $pb->currency, 'created', $checkout['checkout_url'], $now, $now]
            );

            Audit::log('order', (string) $order['public_id'], 'checkout.started', [
                'actor_label' => 'shop_customer',
                'metadata'    => ['total_cents' => $orderTotal, 'shipping_cents' => $shipCost, 'provider' => $gateway->provider()],
            ]);

            return [
                'order_public_id'  => (string) $order['public_id'],
                'checkout_url'     => (string) $checkout['checkout_url'],
                'intent_public_id' => $intentPublic,
                'total_cents'      => $orderTotal,
                'shipping_cents'   => $shipCost,
            ];
        });
    }
}
