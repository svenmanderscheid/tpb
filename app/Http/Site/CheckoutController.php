<?php
declare(strict_types=1);

namespace Tpb\Http\Site;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\Legal\LegalDocRepo;
use Tpb\Domain\Payment\Gateway\TestGateway;
use Tpb\Domain\Payment\PaymentIntentRepo;
use Tpb\Domain\Shop\CheckoutException;
use Tpb\Domain\Shop\CheckoutService;
use Tpb\Domain\Shop\ShopWebhookService;

/**
 * Shop-Checkout (Pfad B, §7). Sofortkauf für Einzelbestellungen: Zusammenfassung mit
 * vollständigem Endpreis (serverseitig), Gastdaten, Pflicht-Checkboxen, „zahlungspflichtig
 * bestellen". Bezahlseite ist im Testmodus eine lokale Simulation des gehosteten Anbieters.
 */
final class CheckoutController
{
    private const REQUIRED = ['agb', 'widerruf'];

    /** @param array<string,string> $params */
    public function form(array $params): void
    {
        $configPublic = (string) (Request::query('config', '') ?? '');
        $config = $configPublic !== '' ? ConfigurationRepo::loadByPublicId($configPublic) : null;
        if ($config === null) {
            throw new HttpException(404, 'Zu diesem Checkout wurde keine Konfiguration gefunden. Bitte zuerst im Konfigurator speichern.');
        }
        Response::html(View::render('site/checkout', [
            'config'    => $config,
            'legalDocs' => LegalDocRepo::allPublished('de'),
            'required'  => self::REQUIRED,
            'error'     => null,
            'old'       => [],
        ], 'layout/site'));
    }

    /** @param array<string,string> $params */
    public function submit(array $params): void
    {
        $configPublic = trim((string) Request::post('config', ''));
        $config = $configPublic !== '' ? ConfigurationRepo::loadByPublicId($configPublic) : null;
        if ($config === null) {
            throw new HttpException(404, 'Konfiguration nicht gefunden.');
        }

        $legalDocs = LegalDocRepo::allPublished('de');
        $accepted = [];
        foreach ($legalDocs as $doc) {
            if (Request::post('consent_' . $doc['doc_type']) === '1') {
                $accepted[] = (string) $doc['doc_type'];
            }
        }
        $old = [
            'type' => (string) (Request::post('type', 'private') ?? 'private'),
            'company_name' => trim((string) Request::post('company_name', '')),
            'first_name' => trim((string) Request::post('first_name', '')),
            'last_name' => trim((string) Request::post('last_name', '')),
            'email' => trim((string) Request::post('email', '')),
            'phone' => trim((string) Request::post('phone', '')),
            'street' => trim((string) Request::post('street', '')),
            'zip' => trim((string) Request::post('zip', '')),
            'city' => trim((string) Request::post('city', '')),
            'country' => strtoupper(trim((string) Request::post('country', ''))),
        ];

        $error = $this->validate($old);
        if ($error !== null) {
            $this->rerender($config, $legalDocs, $error, $old);
            return;
        }

        try {
            $res = CheckoutService::start($configPublic, [
                'type' => $old['type'], 'company_name' => $old['company_name'] !== '' ? $old['company_name'] : null,
                'first_name' => $old['first_name'], 'last_name' => $old['last_name'], 'email' => $old['email'],
                'phone' => $old['phone'] !== '' ? $old['phone'] : null,
                'billing_street' => $old['street'], 'billing_zip' => $old['zip'], 'billing_city' => $old['city'], 'billing_country' => $old['country'],
            ], $accepted);
            Response::redirect($res['checkout_url']);
        } catch (CheckoutException $e) {
            $this->rerender($config, $legalDocs, $e->getMessage(), $old);
        }
    }

    /** Simulierte gehostete Bezahlseite. @param array<string,string> $params */
    public function pay(array $params): void
    {
        $intent = PaymentIntentRepo::findByPublicId((string) ($params['publicId'] ?? ''));
        if ($intent === null) {
            throw new HttpException(404, 'Zahlung nicht gefunden.');
        }
        Response::html(View::render('site/pay', ['intent' => $intent], 'layout/site'));
    }

    /** „Zahlung simulieren": erzeugt ein signiertes Webhook-Event (wie der echte Anbieter). */
    public function simulate(array $params): void
    {
        $intent = PaymentIntentRepo::findByPublicId((string) ($params['publicId'] ?? ''));
        if ($intent === null) {
            throw new HttpException(404, 'Zahlung nicht gefunden.');
        }
        $order = Db::run('SELECT public_id FROM orders WHERE id = ? LIMIT 1', [(int) $intent['order_id']])->fetch();

        $payload = json_encode([
            'event_ref'       => 'evt_' . (string) $intent['provider_ref'],
            'event_type'      => 'payment.succeeded',
            'provider_ref'    => (string) $intent['provider_ref'],
            'amount_cents'    => (int) $intent['amount_cents'],
            'currency'        => (string) $intent['currency'],
            'order_public_id' => $order !== false ? (string) $order['public_id'] : null,
            'ts'              => Clock::nowUtcSeconds(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        ShopWebhookService::handle($payload, TestGateway::sign($payload));

        Response::redirect('/checkout/danke?order=' . rawurlencode($order !== false ? (string) $order['public_id'] : ''));
    }

    /** @param array<string,string> $params */
    public function cancel(array $params): void
    {
        $intent = PaymentIntentRepo::findByPublicId((string) ($params['publicId'] ?? ''));
        if ($intent !== null && (string) $intent['status'] === 'created') {
            PaymentIntentRepo::setStatus((int) $intent['id'], 'canceled', 'customer_cancelled');
            \Tpb\Domain\Stock\StockService::releaseForOrder((int) $intent['order_id']); // Reservierung freigeben
        }
        Response::html(View::render('site/checkout_result', ['kind' => 'cancelled'], 'layout/site'));
    }

    /** @param array<string,string> $params */
    public function thanks(array $params): void
    {
        $orderPublic = (string) (Request::query('order', '') ?? '');
        $order = $orderPublic !== '' ? Db::run('SELECT order_number FROM orders WHERE public_id = ? LIMIT 1', [$orderPublic])->fetch() : false;
        Response::html(View::render('site/checkout_result', [
            'kind' => 'paid',
            'order_number' => $order !== false ? (string) $order['order_number'] : '',
        ], 'layout/site'));
    }

    /** @param array<string,mixed> $config @param array<int,array<string,mixed>> $legalDocs @param array<string,mixed> $old */
    private function rerender(array $config, array $legalDocs, string $error, array $old): void
    {
        Response::html(View::render('site/checkout', [
            'config' => $config, 'legalDocs' => $legalDocs, 'required' => self::REQUIRED, 'error' => $error, 'old' => $old,
        ], 'layout/site'), 422);
    }

    /** @param array<string,mixed> $d */
    private function validate(array $d): ?string
    {
        if ($d['first_name'] === '' || $d['last_name'] === '') {
            return 'Bitte Vor- und Nachnamen angeben.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if ($d['street'] === '' || $d['zip'] === '' || $d['city'] === '') {
            return 'Bitte die vollständige Rechnungsadresse angeben.';
        }
        return null;
    }
}
