<?php
declare(strict_types=1);

namespace Tpb\Domain\Payment\Gateway;

use Tpb\Core\Env;

/**
 * Test-Zahlungsadapter (Owner 2026-08-16): simuliert einen gehosteten Anbieter lokal.
 * Der Checkout verweist auf eine interne Bezahlseite; „Zahlung simulieren" erzeugt ein
 * HMAC-signiertes Webhook-Event – genau der Weg, den ein echter Anbieter server-to-server
 * gehen würde. So ist der komplette Pfad-B-Flow ohne externen Anbieter end-to-end testbar.
 */
final class TestGateway implements PaymentGateway
{
    public const PROVIDER = 'test';

    public function provider(): string
    {
        return self::PROVIDER;
    }

    public function createCheckout(string $intentPublicId, int $amountCents, string $currency): array
    {
        $base = rtrim((string) (Env::get('APP_URL', 'http://tpb.local') ?? 'http://tpb.local'), '/');
        return [
            'provider_ref' => 'test_' . $intentPublicId,
            'checkout_url' => $base . '/pay/' . $intentPublicId,
        ];
    }

    public function verifySignature(string $rawBody, string $signature): bool
    {
        $expected = self::sign($rawBody);
        return $signature !== '' && hash_equals($expected, $signature);
    }

    /** HMAC-SHA256 über den Rohtext mit dem Webhook-Secret (Test). */
    public static function sign(string $rawBody): string
    {
        $secret = (string) (Env::get('PAYMENT_WEBHOOK_SECRET', 'local-test-secret') ?? 'local-test-secret');
        return hash_hmac('sha256', $rawBody, $secret !== '' ? $secret : 'local-test-secret');
    }
}
