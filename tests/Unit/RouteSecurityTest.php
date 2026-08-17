<?php
declare(strict_types=1);

namespace Tpb\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Sicherheits-Checkliste §11 (deklarativ gegen routes.php): jede mutierende Admin-Route
 * hat CSRF + Auth; kritische Aktionen zusätzlich sudo. Verhindert, dass eine neue Route
 * ohne Schutz durchrutscht.
 */
final class RouteSecurityTest extends TestCase
{
    /** @return array<int,array{0:string,1:string,2:mixed,3:array<int,string>}> */
    private static function routes(): array
    {
        /** @var array<int,array{0:string,1:string,2:mixed,3?:array<int,string>}> $routes */
        $routes = require dirname(__DIR__, 2) . '/app/Http/routes.php';
        return array_map(static fn ($r) => [$r[0], $r[1], $r[2], $r[3] ?? []], $routes);
    }

    public function testEveryMutatingAdminRouteHasCsrfAndAuth(): void
    {
        foreach (self::routes() as [$method, $path, , $tags]) {
            if (!str_starts_with($path, '/admin')) {
                continue;
            }
            // Ausdrücklich öffentliche Endpunkte (Login/MFA-Login) sind bewusst ohne Auth.
            if (in_array('public', $tags, true)) {
                continue;
            }
            $hasAuth = in_array('auth', $tags, true) || (bool) array_filter($tags, static fn ($t) => str_starts_with($t, 'auth:'));
            self::assertTrue($hasAuth, "Admin-Route ohne Auth: {$method} {$path}");
            if ($method === 'POST') {
                self::assertContains('csrf', $tags, "Mutierende Admin-Route ohne CSRF: {$path}");
            }
        }
    }

    public function testCriticalActionsRequireSudo(): void
    {
        $critical = [
            '/admin/rechnung/{publicId}/ausstellen',
            '/admin/rechnung/{publicId}/gutschrift',
            '/admin/preisbuch/{version}/publish',
            '/admin/kostenversion/{version}/publish',
        ];
        $bySudo = [];
        foreach (self::routes() as [, $path, , $tags]) {
            if (in_array('sudo', $tags, true)) {
                $bySudo[$path] = true;
            }
        }
        foreach ($critical as $path) {
            self::assertArrayHasKey($path, $bySudo, "Kritische Aktion ohne sudo: {$path}");
        }
    }

    public function testPublicMutatingRoutesStillHaveCsrf(): void
    {
        // Session-gebundene öffentliche POST-Formulare (Login, Checkout, Angebot/Proof)
        // brauchen CSRF. Ausgenommen: der signaturgeprüfte Webhook (server-to-server) und
        // die zustandslosen JSON-APIs unter /api/ (kein Session-Kontext, kein CSRF-Vektor).
        foreach (self::routes() as [$method, $path, , $tags]) {
            if ($method !== 'POST' || !in_array('public', $tags, true)) {
                continue;
            }
            if ($path === '/webhooks/payment' || str_starts_with($path, '/api/')) {
                continue;
            }
            self::assertContains('csrf', $tags, "Öffentliche mutierende Route ohne CSRF: {$path}");
        }
    }
}
