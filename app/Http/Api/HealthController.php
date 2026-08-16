<?php
declare(strict_types=1);

namespace Tpb\Http\Api;

use Tpb\Core\Clock;
use Tpb\Core\Response;
use Tpb\Domain\Ops\HealthCheck;

/**
 * Öffentlicher /health-Endpunkt (§ M7) für den externen Uptime-Monitor. Enthält
 * KEINE sensiblen Daten – nur Status + Zeitstempel. 200 bei ok, 503 bei degraded.
 */
final class HealthController
{
    /** @param array<string,string> $params */
    public function health(array $params): void
    {
        $live = HealthCheck::liveness();
        Response::json([
            'status' => $live['status'],
            'time'   => Clock::nowUtc()->format('c'),
        ], $live['status'] === 'ok' ? 200 : 503);
    }
}
