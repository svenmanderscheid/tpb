<?php
declare(strict_types=1);

namespace Tpb\Domain\Quote;

use Tpb\Core\Canonical;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Config\ConfigMapper;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\Customer\CustomerRepo;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\Legal\LegalDocRepo;
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Outbox\MailTemplates;
use Tpb\Domain\Outbox\Outbox;
use Tpb\Domain\Pdf\PdfService;
use Tpb\Domain\Pricing\PriceEngine;
use Tpb\Domain\Pricing\PricingRepo;
use Tpb\Domain\Sequence\NumberSequence;
use Tpb\Domain\Status\Status;
use Tpb\Domain\Token\AccessTokenService;

/**
 * Angebots-Lebenszyklus (§7): Erstellen (Snapshot einfrieren) → Versenden
 * (Q-Nummer, PDF, Token, Outbox-Mail) → Annahme (idempotent genau eine Order)
 * oder Ablehnung. Preise werden IMMER serverseitig gerechnet; der Snapshot ist
 * ab Erstellung unveränderlich – spätere Preisbuchänderungen wirken nicht zurück.
 */
final class QuoteService
{
    public const TOKEN_PURPOSE = 'quote_view';

    /**
     * Erzeugt aus einer eingereichten Konfiguration ein Angebot im Status DRAFT
     * inkl. eingefrorenem Snapshot und quote_items. Rechnet gegen das aktuell
     * veröffentlichte Preisbuch und friert das Ergebnis ein.
     *
     * @return array{id:int,public_id:string}
     */
    public static function createFromConfiguration(int $configId, ?int $actorUserId): array
    {
        $cfg = Db::run('SELECT id, public_id, customer_id, status FROM configurations WHERE id = ? LIMIT 1', [$configId])->fetch();
        if ($cfg === false) {
            throw new \InvalidArgumentException('Konfiguration nicht gefunden.');
        }
        if ($cfg['customer_id'] === null) {
            throw new \InvalidArgumentException('Zur Konfiguration ist kein Kunde hinterlegt.');
        }
        $customer = CustomerRepo::findById((int) $cfg['customer_id']);
        if ($customer === null) {
            throw new \InvalidArgumentException('Kunde nicht gefunden.');
        }

        // Preis serverseitig neu berechnen (bindet das Angebot an das aktive Preisbuch).
        $payload = ConfigurationRepo::loadByPublicId((string) $cfg['public_id']);
        if ($payload === null) {
            throw new \InvalidArgumentException('Konfiguration konnte nicht geladen werden.');
        }
        $resolved = ConfigMapper::resolve($payload);
        $pb = PricingRepo::activePriceBook();
        $cv = PricingRepo::activeCostVersion();
        $ids = PricingRepo::activeIds();
        if ($pb === null || $cv === null || $ids === null) {
            throw new \RuntimeException('Kein veröffentlichtes Preisbuch/keine Kostenversion vorhanden.');
        }
        $engineConfig = ConfigMapper::toEngineConfig($resolved);
        $breakdown = PriceEngine::calculate($engineConfig, $pb, $cv);

        $snapshot = self::buildSnapshot($payload, $resolved, $breakdown, $pb, $customer);
        $snapshotJson = Canonical::json($snapshot);
        $snapshotHash = hash('sha256', $snapshotJson);

        $validUntil = Clock::nowUtc()->modify('+' . self::expiryDays() . ' days')->format('Y-m-d');

        return Db::tx(function () use ($configId, $cfg, $customer, $breakdown, $snapshot, $snapshotJson, $snapshotHash, $pb, $ids, $engineConfig, $validUntil, $actorUserId): array {
            $now = Clock::nowUtcSeconds();

            // Frische Berechnung persistieren (Nachvollziehbarkeit, §6).
            ConfigurationRepo::persistCalculation(
                $configId, $ids['price_book_id'], $ids['cost_version_id'], Canonical::hash($engineConfig),
                json_encode($breakdown->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $breakdown->totalCents, $breakdown->floorCents, $breakdown->belowFloor, $breakdown->calcHash
            );

            $publicId = Ulid::generate();
            Db::run(
                'INSERT INTO quotes
                    (public_id, quote_number, customer_id, configuration_id, status, currency,
                     total_cents, valid_until, snapshot_json, snapshot_sha256, created_by, created_at, updated_at)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $publicId, (int) $customer['id'], $configId, 'DRAFT', $pb->currency,
                    $breakdown->totalCents, $validUntil, $snapshotJson, $snapshotHash, $actorUserId, $now, $now,
                ]
            );
            $quoteId = (int) Db::pdo()->lastInsertId();

            foreach ($snapshot['lines'] as $line) {
                Db::run(
                    'INSERT INTO quote_items (quote_id, pos_no, sku, description, qty, unit_cents, line_cents, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$quoteId, $line['pos_no'], $line['sku'], $line['description'], $line['qty'], $line['unit_cents'], $line['line_cents'], $now, $now]
                );
            }

            Db::run("UPDATE configurations SET status = 'quoted', updated_at = ? WHERE id = ?", [$now, $configId]);

            Status::transition('quote', $quoteId, 'quote', null, 'DRAFT', ['actor_user_id' => $actorUserId]);
            Audit::log('quote', $publicId, 'quote.created', [
                'actor_user_id' => $actorUserId,
                'to_state'      => 'DRAFT',
                'metadata'      => ['total_cents' => $breakdown->totalCents, 'snapshot_sha256' => $snapshotHash],
            ]);

            return ['id' => $quoteId, 'public_id' => $publicId];
        });
    }

    /**
     * Versendet ein DRAFT-Angebot: vergibt die Q-Nummer, rendert das PDF aus dem
     * Snapshot, hinterlegt Token und Outbox-Mail und setzt den Status auf SENT.
     *
     * @return array{quote_number:string,token:string,pdf_public_id:string}
     */
    public static function send(int $quoteId, ?int $actorUserId): array
    {
        return Db::tx(function () use ($quoteId, $actorUserId): array {
            $quote = Db::run('SELECT * FROM quotes WHERE id = ? FOR UPDATE', [$quoteId])->fetch();
            if ($quote === false) {
                throw new \InvalidArgumentException('Angebot nicht gefunden.');
            }
            if ((string) $quote['status'] !== 'DRAFT') {
                throw new \RuntimeException('Nur Entwürfe können versendet werden.');
            }

            $snapshot = json_decode((string) $quote['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $customer = CustomerRepo::findById((int) $quote['customer_id']);
            $now = Clock::nowUtcSeconds();

            $quoteNumber = NumberSequence::next('quote', 'Q');

            $pdfBytes = PdfService::render('quote', [
                'quote'    => $quote,
                'number'   => $quoteNumber,
                'snapshot' => $snapshot,
            ]);
            $asset = AssetService::storeGenerated(
                $pdfBytes, "Angebot_{$quoteNumber}.pdf", 'application/pdf', 'quote_pdf', 'proof_contract',
                $actorUserId, 'customer', (int) $quote['customer_id']
            );

            $token = AccessTokenService::issue(self::TOKEN_PURPOSE, 'quote', $quoteId);

            Db::run(
                "UPDATE quotes SET quote_number = ?, status = 'SENT', pdf_asset_id = ?, sent_at = ?, updated_at = ? WHERE id = ?",
                [$quoteNumber, $asset['id'], $now, $now, $quoteId]
            );

            Status::transition('quote', $quoteId, 'quote', 'DRAFT', 'SENT', ['actor_user_id' => $actorUserId]);

            $mail = [
                'quote_id'        => $quoteId,
                'quote_public_id' => (string) $quote['public_id'],
                'quote_number'    => $quoteNumber,
                'token'           => $token,
                'to_email'        => $customer['email'] ?? null,
                'to_name'         => trim((string) ($customer['first_name'] ?? '') . ' ' . (string) ($customer['last_name'] ?? '')),
                'total_cents'     => (int) $quote['total_cents'],
                'currency'        => (string) $quote['currency'],
            ];
            Outbox::enqueue('mail.quote_sent', array_merge($mail, MailTemplates::quoteSent($mail)), 'quote_sent_' . $quoteId);

            Audit::log('quote', (string) $quote['public_id'], 'quote.sent', [
                'actor_user_id' => $actorUserId,
                'from_state'    => 'DRAFT',
                'to_state'      => 'SENT',
                'metadata'      => ['quote_number' => $quoteNumber],
            ]);

            return ['quote_number' => $quoteNumber, 'token' => $token, 'pdf_public_id' => (string) $asset['public_id']];
        });
    }

    /**
     * Kundenannahme (§7): idempotent. Erzeugt genau eine Order aus dem Snapshot.
     * Bei Doppelklick liefert der zweite Aufruf dieselbe Order (FOR UPDATE + Statusprüfung).
     *
     * @return array{order_id:int,order_public_id:string,order_number:string,already:bool}
     */
    public static function accept(string $quotePublicId, string $rawToken): array
    {
        // Der Ablauf-Fall muss den EXPIRED-Wechsel COMMITTEN und die Aktion abweisen.
        // Da ein throw in der Transaktion zurückrollen würde, signalisieren wir Ablauf
        // über den Rückgabewert und werfen erst nach dem Commit.
        $outcome = Db::tx(function () use ($quotePublicId, $rawToken): array {
            $quote = Db::run('SELECT * FROM quotes WHERE public_id = ? FOR UPDATE', [$quotePublicId])->fetch();
            if ($quote === false) {
                throw new QuoteAccessException('Angebot nicht gefunden.');
            }
            $quoteId = (int) $quote['id'];

            if (AccessTokenService::verify($rawToken, self::TOKEN_PURPOSE, 'quote', $quoteId) === null) {
                throw new QuoteAccessException('Der Angebotslink ist ungültig, abgelaufen oder widerrufen.');
            }

            // Idempotenz: bereits angenommen → bestehende Order zurückgeben.
            if ((string) $quote['status'] === 'ACCEPTED') {
                $order = OrderRepo::findByQuoteId($quoteId);
                if ($order !== null) {
                    return [
                        'result' => 'ok', 'order_id' => (int) $order['id'], 'order_public_id' => (string) $order['public_id'],
                        'order_number' => (string) $order['order_number'], 'already' => true,
                    ];
                }
            }
            if ((string) $quote['status'] !== 'SENT') {
                throw new QuoteStateException('Dieses Angebot kann nicht mehr angenommen werden (Status: ' . (string) $quote['status'] . ').');
            }
            // Gültigkeit prüfen.
            if ($quote['valid_until'] !== null && (string) $quote['valid_until'] < Clock::nowUtc()->format('Y-m-d')) {
                Db::run("UPDATE quotes SET status = 'EXPIRED', updated_at = ? WHERE id = ?", [Clock::nowUtcSeconds(), $quoteId]);
                Status::transition('quote', $quoteId, 'quote', 'SENT', 'EXPIRED', ['actor_label' => 'system', 'reason' => 'valid_until_passed']);
                return ['result' => 'expired'];
            }

            $snapshot = json_decode((string) $quote['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $order = OrderRepo::createFromQuote($quote, $snapshot, null);

            // Rechtserklärungen zum Zeitpunkt der Annahme festhalten (order_terms_acceptance).
            self::recordTermsAcceptance($quoteId, (int) $order['id'], $customerLabel = self::customerLabel($snapshot));

            Db::run("UPDATE quotes SET status = 'ACCEPTED', accepted_at = ?, updated_at = ? WHERE id = ?", [Clock::nowUtcSeconds(), Clock::nowUtcSeconds(), $quoteId]);
            Status::transition('quote', $quoteId, 'quote', 'SENT', 'ACCEPTED', ['actor_label' => 'customer']);

            // Token NICHT entwerten: Der Kundenlink bleibt für Doppelklick-Idempotenz
            // und spätere Einsicht gültig (läuft ohnehin nach TTL ab).

            $omail = [
                'order_id'        => (int) $order['id'],
                'order_public_id' => (string) $order['public_id'],
                'order_number'    => (string) $order['order_number'],
                'to_email'        => $snapshot['customer']['email'] ?? null,
                'to_name'         => $customerLabel,
                'total_cents'     => (int) ($snapshot['totals']['total_cents'] ?? 0),
                'currency'        => (string) $quote['currency'],
            ];
            Outbox::enqueue('mail.order_confirmed', array_merge($omail, MailTemplates::orderConfirmed($omail)), 'order_confirmed_' . (int) $order['id']);

            Audit::log('quote', $quotePublicId, 'quote.accepted', [
                'actor_label' => 'customer',
                'from_state'  => 'SENT',
                'to_state'    => 'ACCEPTED',
                'metadata'    => ['order_number' => $order['order_number']],
            ]);

            return [
                'result' => 'ok', 'order_id' => (int) $order['id'], 'order_public_id' => (string) $order['public_id'],
                'order_number' => (string) $order['order_number'], 'already' => false,
            ];
        });

        if (($outcome['result'] ?? null) === 'expired') {
            throw new QuoteStateException('Das Angebot ist abgelaufen.');
        }

        return [
            'order_id'        => (int) $outcome['order_id'],
            'order_public_id' => (string) $outcome['order_public_id'],
            'order_number'    => (string) $outcome['order_number'],
            'already'         => (bool) $outcome['already'],
        ];
    }

    public static function decline(string $quotePublicId, string $rawToken): void
    {
        Db::tx(function () use ($quotePublicId, $rawToken): void {
            $quote = Db::run('SELECT * FROM quotes WHERE public_id = ? FOR UPDATE', [$quotePublicId])->fetch();
            if ($quote === false) {
                throw new QuoteAccessException('Angebot nicht gefunden.');
            }
            $quoteId = (int) $quote['id'];
            if (AccessTokenService::verify($rawToken, self::TOKEN_PURPOSE, 'quote', $quoteId) === null) {
                throw new QuoteAccessException('Der Angebotslink ist ungültig, abgelaufen oder widerrufen.');
            }
            if (in_array((string) $quote['status'], ['DECLINED', 'ACCEPTED'], true)) {
                return; // idempotent
            }
            if ((string) $quote['status'] !== 'SENT') {
                throw new QuoteStateException('Dieses Angebot kann nicht mehr abgelehnt werden.');
            }
            $now = Clock::nowUtcSeconds();
            Db::run("UPDATE quotes SET status = 'DECLINED', declined_at = ?, updated_at = ? WHERE id = ?", [$now, $now, $quoteId]);
            Status::transition('quote', $quoteId, 'quote', 'SENT', 'DECLINED', ['actor_label' => 'customer']);
            Audit::log('quote', $quotePublicId, 'quote.declined', ['actor_label' => 'customer', 'from_state' => 'SENT', 'to_state' => 'DECLINED']);
        });
    }

    /**
     * Baut den unveränderlichen Snapshot (Kunde + Positionen + Summen). Preiswerte
     * stammen aus dem Breakdown (§6); beschreibende Daten aus der Konfiguration.
     * Index i ist über payload/resolved/breakdown identisch (gleiche Reihenfolge).
     *
     * @param array<string,mixed> $payload   ConfigurationRepo::loadByPublicId (externe Schlüssel)
     * @param array<string,mixed> $resolved  ConfigMapper::resolve (interne IDs)
     * @param array<string,mixed> $customer
     * @return array<string,mixed>
     */
    private static function buildSnapshot(array $payload, array $resolved, \Tpb\Domain\Pricing\Breakdown $breakdown, \Tpb\Domain\Pricing\PriceBook $pb, array $customer): array
    {
        $names = self::productNames(array_map(static fn ($it) => (int) $it['product_id'], $resolved['items']));

        $lines = [];
        $orderItems = [];
        $pos = 0;
        foreach ($resolved['items'] as $i => $rit) {
            $pit = $payload['items'][$i];
            $b = $breakdown->items[$i];
            $prod = $names[(int) $rit['product_id']] ?? ['name' => 'Position', 'sku_root' => null];

            $qty = (int) $b['qty'];
            $unitPiece = (int) $b['unit_base_cents'] + (int) $b['piece_surcharge_cents'];
            $productLine = $qty * $unitPiece;

            $desc = (string) $prod['name'];
            if (($rit['type'] ?? 'configured') === 'configured') {
                $extra = [];
                if (!empty($rit['technique_code'])) {
                    $extra[] = (string) $rit['technique_code'];
                }
                $placements = [];
                foreach ($pit['layers'] ?? [] as $l) {
                    $placements[(string) $l['placement_code']] = true;
                }
                if ($placements !== []) {
                    $extra[] = count($placements) . ' Position(en)';
                }
                if ($extra !== []) {
                    $desc .= ' (' . implode(', ', $extra) . ')';
                }
            }

            $lines[] = ['pos_no' => ++$pos, 'sku' => $prod['sku_root'], 'description' => $desc, 'qty' => $qty, 'unit_cents' => $unitPiece, 'line_cents' => $productLine];

            $orderItems[] = [
                'type'        => (string) ($rit['type'] ?? 'configured'),
                'product_id'  => (int) $rit['product_id'],
                'sku'         => $prod['sku_root'],
                'description' => $desc,
                'qty'         => $qty,
                'unit_cents'  => $unitPiece,
                'line_cents'  => $productLine,
                'config'      => $pit,
                'units'       => array_map(static fn ($u) => [
                    'variant_sku' => (string) $u['variant_sku'],
                    'name'        => $u['name'] ?? null,
                    'number'      => $u['number'] ?? null,
                ], $pit['units'] ?? []),
            ];

            $perso = (int) $b['personalisation_cents'];
            if ($perso > 0) {
                $persoCount = 0;
                foreach ($pit['units'] ?? [] as $u) {
                    if (trim((string) ($u['name'] ?? '')) !== '' || trim((string) ($u['number'] ?? '')) !== '') {
                        $persoCount++;
                    }
                }
                $unit = $persoCount > 0 ? intdiv($perso, $persoCount) : $perso;
                $lines[] = ['pos_no' => ++$pos, 'sku' => null, 'description' => 'Personalisierung (Name/Nummer) – ' . (string) $prod['name'], 'qty' => max(1, $persoCount), 'unit_cents' => $unit, 'line_cents' => $perso];
            }
        }

        if ($breakdown->setupsCents > 0) {
            $lines[] = ['pos_no' => ++$pos, 'sku' => null, 'description' => 'Einrichtung (Setup je Motiv)', 'qty' => 1, 'unit_cents' => $breakdown->setupsCents, 'line_cents' => $breakdown->setupsCents];
        }
        if ($breakdown->filePrepCents > 0) {
            $lines[] = ['pos_no' => ++$pos, 'sku' => null, 'description' => 'Dateiaufbereitung', 'qty' => 1, 'unit_cents' => $breakdown->filePrepCents, 'line_cents' => $breakdown->filePrepCents];
        }
        if ($breakdown->expressCents > 0) {
            $lines[] = ['pos_no' => ++$pos, 'sku' => null, 'description' => 'Express-Zuschlag', 'qty' => 1, 'unit_cents' => $breakdown->expressCents, 'line_cents' => $breakdown->expressCents];
        }

        return [
            'customer' => [
                'public_id'    => (string) $customer['public_id'],
                'type'         => (string) $customer['type'],
                'company_name' => $customer['company_name'] ?? null,
                'first_name'   => (string) $customer['first_name'],
                'last_name'    => (string) $customer['last_name'],
                'email'        => (string) $customer['email'],
                'phone'        => $customer['phone'] ?? null,
                'lang'         => (string) $customer['lang'],
                'billing'      => [
                    'street'  => $customer['billing_street'] ?? null,
                    'zip'     => $customer['billing_zip'] ?? null,
                    'city'    => $customer['billing_city'] ?? null,
                    'country' => $customer['billing_country'] ?? null,
                ],
            ],
            'currency'           => $pb->currency,
            'price_book_version' => $pb->version,
            'lines'              => $lines,
            'items'              => $orderItems,
            'totals'             => [
                'subtotal_cents'  => $breakdown->subtotalCents,
                'setups_cents'    => $breakdown->setupsCents,
                'fileprep_cents'  => $breakdown->filePrepCents,
                'express_cents'   => $breakdown->expressCents,
                'shipping_cents'  => $breakdown->shippingCents,
                'total_cents'     => $breakdown->totalCents,
                'floor_cents'     => $breakdown->floorCents,
                'below_floor'     => $breakdown->belowFloor,
                'below_min_order' => $breakdown->belowMinOrder,
                'calc_hash'       => $breakdown->calcHash,
            ],
        ];
    }

    /**
     * @param array<int,int> $productIds
     * @return array<int,array{name:string,sku_root:?string}>
     */
    private static function productNames(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter($productIds)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach (Db::run("SELECT id, name, sku_root FROM products WHERE id IN ({$in})", $ids)->fetchAll() as $r) {
            $out[(int) $r['id']] = ['name' => (string) $r['name'], 'sku_root' => (string) $r['sku_root']];
        }
        return $out;
    }

    private static function customerLabel(array $snapshot): string
    {
        $c = $snapshot['customer'] ?? [];
        $name = trim((string) ($c['first_name'] ?? '') . ' ' . (string) ($c['last_name'] ?? ''));
        return $name !== '' ? $name : (string) ($c['company_name'] ?? 'Kunde');
    }

    /** Hält die zum Annahmezeitpunkt veröffentlichten Rechtstexte als akzeptiert fest. */
    private static function recordTermsAcceptance(int $quoteId, int $orderId, string $actorLabel): void
    {
        $now = Clock::nowUtcSeconds();
        foreach (LegalDocRepo::allPublished('de') as $doc) {
            Db::run(
                'INSERT INTO order_terms_acceptance (order_id, quote_id, doc_type, legal_doc_version_id, shown_at, accepted_at, actor_label, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $quoteId, (string) $doc['doc_type'], (int) $doc['id'], $now, $now, $actorLabel, $now]
            );
        }
    }

    private static function expiryDays(): int
    {
        $row = Db::run("SELECT value_json FROM business_settings WHERE setting_key = 'reminder.quote_expiry_days' LIMIT 1")->fetch();
        if ($row === false) {
            return 14;
        }
        $v = json_decode((string) $row['value_json'], true);
        return is_int($v) && $v > 0 ? $v : 14;
    }
}
