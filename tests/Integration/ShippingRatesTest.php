<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Shipping\ShippingRates;

/**
 * Versandkosten-Regel (Owner 2026-08-16): national Post LU (gratis ab 50 €),
 * international DHL. Beträge aus business_settings.
 */
final class ShippingRatesTest extends TestCase
{
    protected function setUp(): void
    {
        Db::run("DELETE FROM business_settings WHERE setting_key LIKE 'shipping.%'");
        $now = Clock::nowUtcSeconds();
        foreach ([
            'shipping.national_cents' => '500',
            'shipping.international_cents' => '1500',
            'shipping.free_national_threshold_cents' => '5000',
            'shipping.national_carrier' => '"Post Luxembourg"',
            'shipping.international_carrier' => '"DHL"',
        ] as $k => $v) {
            Db::run('INSERT INTO business_settings (setting_key, value_json, updated_at) VALUES (?, ?, ?)', [$k, $v, $now]);
        }
    }

    protected function tearDown(): void
    {
        Db::run("DELETE FROM business_settings WHERE setting_key LIKE 'shipping.%'");
    }

    public function testNationalFreeAboveThreshold(): void
    {
        $r = ShippingRates::costFor('LU', 6000);
        self::assertSame('national', $r['zone']);
        self::assertSame('Post Luxembourg', $r['carrier']);
        self::assertSame(0, $r['cost_cents']);
        self::assertTrue($r['free_applied']);
    }

    public function testNationalPaidBelowThreshold(): void
    {
        $r = ShippingRates::costFor('LU', 3000);
        self::assertSame('national', $r['zone']);
        self::assertSame(500, $r['cost_cents']);
        self::assertFalse($r['free_applied']);
    }

    public function testInternationalAlwaysPaid(): void
    {
        self::assertSame(1500, ShippingRates::costFor('DE', 3000)['cost_cents']);
        self::assertSame(1500, ShippingRates::costFor('DE', 100000)['cost_cents']); // keine Gratis-Schwelle international
        self::assertSame('DHL', ShippingRates::costFor('FR', 3000)['carrier']);
    }

    public function testEmptyCountryTreatedAsNational(): void
    {
        self::assertSame('national', ShippingRates::costFor('', 3000)['zone']);
    }
}
