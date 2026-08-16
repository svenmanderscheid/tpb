<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Domain\Ops\HealthCheck;

/**
 * Health-Check: Liveness (DB) + Detailprüfung liefert alle Checks mit ok-Flag.
 */
final class HealthCheckTest extends TestCase
{
    public function testLivenessOkWhenDbReachable(): void
    {
        self::assertSame('ok', HealthCheck::liveness()['status']);
    }

    public function testFullReturnsAllChecks(): void
    {
        $full = HealthCheck::full();
        self::assertArrayHasKey('ok', $full);
        self::assertIsBool($full['ok']);
        $names = array_map(static fn ($c) => $c['name'], $full['checks']);
        self::assertSame(['db', 'backup', 'outbox', 'disk', 'quarantine'], $names);
        // DB-Check muss ok sein (Testverbindung besteht).
        $db = array_values(array_filter($full['checks'], static fn ($c) => $c['name'] === 'db'))[0];
        self::assertTrue($db['ok']);
    }
}
