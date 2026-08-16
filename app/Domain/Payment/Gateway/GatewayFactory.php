<?php
declare(strict_types=1);

namespace Tpb\Domain\Payment\Gateway;

use Tpb\Core\Env;

/**
 * Liefert den aktiven Zahlungsadapter (PAYMENT_PROVIDER, §5.7). Derzeit nur der
 * Test-Adapter; echte Anbieter werden hier registriert, sobald API-Zugang besteht.
 */
final class GatewayFactory
{
    public static function active(): PaymentGateway
    {
        $provider = (string) (Env::get('PAYMENT_PROVIDER', 'test') ?? 'test');
        return self::byProvider($provider !== '' ? $provider : 'test');
    }

    public static function byProvider(string $provider): PaymentGateway
    {
        return match ($provider) {
            TestGateway::PROVIDER => new TestGateway(),
            default               => new TestGateway(), // Fallback: bis ein echter Anbieter integriert ist
        };
    }
}
