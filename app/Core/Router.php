<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Router (§4). routes.php liefert Zeilen [method, pattern, [Class,'action'], [tags]].
 * Pattern-Parameter {name} -> [A-Za-z0-9_-]+. Middleware-Tags:
 * public, auth[:<capability>], csrf, rate:<bucket>.
 */
final class Router
{
    /**
     * @param array<int,array{0:string,1:string,2:array{0:class-string,1:string},3?:string[]}> $routes
     */
    public static function dispatch(array $routes): void
    {
        Response::sendSecurityHeaders();
        Auth::startSession();
        Auth::enforceTimeouts();

        $method = Request::method();
        $path = self::normalizePath(Request::path());
        $methodMismatch = false;

        foreach ($routes as $route) {
            $pattern = self::normalizePath($route[1]);
            $regex = self::compile($pattern);
            if (preg_match($regex, $path, $matches) !== 1) {
                continue;
            }
            if ($route[0] !== $method) {
                $methodMismatch = true;
                continue;
            }
            $params = array_filter(
                $matches,
                static fn ($k) => is_string($k),
                ARRAY_FILTER_USE_KEY
            );
            self::applyMiddleware($route[3] ?? []);
            [$class, $action] = $route[2];
            (new $class())->{$action}($params);
            return;
        }

        Response::error($methodMismatch ? 405 : 404);
    }

    /**
     * @param string[] $tags
     */
    private static function applyMiddleware(array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag === 'public') {
                continue;
            }
            if ($tag === 'csrf') {
                Csrf::check();
                continue;
            }
            if ($tag === 'auth' || str_starts_with($tag, 'auth:')) {
                if (!Auth::check()) {
                    Response::redirect('/admin/login');
                    exit;
                }
                $capability = str_contains($tag, ':') ? substr($tag, 5) : null;
                if ($capability !== null && $capability !== '') {
                    Authz::require($capability);
                }
                continue;
            }
            if (str_starts_with($tag, 'rate:')) {
                $bucket = substr($tag, 5);
                RateLimit::enforce($bucket, Request::ip());
                continue;
            }
            if ($tag === 'sudo') {
                self::enforceSudo();
                continue;
            }
            throw new \RuntimeException("Unknown middleware tag: {$tag}");
        }
    }

    /**
     * sudo-Modus (§11): ist die Bestätigung frisch (≤5 min), passiert nichts. Sonst wird
     * eine Bestätigungsseite gerendert, die die ursprüngliche Aktion (inkl. aller POST-Felder)
     * zusammen mit einem Passwort-/TOTP-Feld an dieselbe URL erneut sendet.
     */
    private static function enforceSudo(): void
    {
        if (\Tpb\Domain\Auth\Sudo::isFresh()) {
            return;
        }
        $uid = Auth::id();
        $pw = Request::post('_sudo_password');
        if ($uid !== null && $pw !== null && $pw !== '' && \Tpb\Domain\Auth\Sudo::confirm((int) $uid, $pw)) {
            return; // Bestätigung erfolgreich – Aktion darf laufen
        }

        $fields = [];
        foreach ($_POST as $k => $v) {
            if ($k === '_sudo_password' || $k === '_csrf' || !is_string($v)) {
                continue;
            }
            $fields[$k] = $v;
        }
        Response::html(View::render('admin/sudo', [
            'action' => Request::path(),
            'fields' => $fields,
            'error'  => ($pw !== null && $pw !== '') ? 'Bestätigung fehlgeschlagen. Bitte erneut versuchen.' : null,
        ], null), 200);
        exit;
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path;
    }

    private static function compile(string $pattern): string
    {
        $regex = preg_replace(
            '#\{([A-Za-z_][A-Za-z0-9_]*)\}#',
            '(?P<$1>[A-Za-z0-9_-]+)',
            $pattern
        );
        return '#^' . $regex . '$#';
    }
}
