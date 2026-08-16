<?php
declare(strict_types=1);

namespace Tpb\Domain\Shipping;

use Tpb\Core\Db;

/**
 * Versandkosten-Regel (Owner 2026-08-16): national (LU) = Post Luxembourg, international
 * = DHL; Kosten sind im Preis einkalkuliert; national gratis ab Schwellenwert (50 €).
 * Beträge/Carrier aus business_settings (Platzhalter, admin-/DB-pflegbar) – nie hart codiert.
 */
final class ShippingRates
{
    /** @return array<string,mixed> {zone,carrier,method,cost_cents,free_threshold_cents,free_applied} */
    public static function costFor(?string $country, int $subtotalCents): array
    {
        $s = self::settings();
        $isNational = $country === null || $country === '' || strtoupper($country) === 'LU';

        if ($isNational) {
            $threshold = (int) $s['free_national_threshold_cents'];
            $free = $threshold > 0 && $subtotalCents >= $threshold;
            return [
                'zone'      => 'national',
                'carrier'   => (string) $s['national_carrier'],
                'method'    => 'carrier_standard',
                'cost_cents' => $free ? 0 : (int) $s['national_cents'],
                'free_threshold_cents' => $threshold,
                'free_applied' => $free,
            ];
        }

        return [
            'zone'      => 'international',
            'carrier'   => (string) $s['international_carrier'],
            'method'    => 'carrier_standard',
            'cost_cents' => (int) $s['international_cents'],
            'free_threshold_cents' => 0,
            'free_applied' => false,
        ];
    }

    /** @return array<string,mixed> */
    private static function settings(): array
    {
        $defaults = [
            'national_cents'                 => 500,
            'international_cents'             => 1500,
            'free_national_threshold_cents'  => 5000,
            'national_carrier'               => 'Post Luxembourg',
            'international_carrier'           => 'DHL',
        ];
        $rows = Db::run(
            "SELECT setting_key, value_json FROM business_settings WHERE setting_key LIKE 'shipping.%'"
        )->fetchAll();
        foreach ($rows as $r) {
            $key = substr((string) $r['setting_key'], strlen('shipping.'));
            if (array_key_exists($key, $defaults)) {
                $defaults[$key] = json_decode((string) $r['value_json'], true);
            }
        }
        return $defaults;
    }
}
