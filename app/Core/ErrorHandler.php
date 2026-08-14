<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Zentraler Fehler-Handler (§3.9). Nutzer sehen generische Meldung +
 * Correlation-ID; Details nur ins Log (private/tpb/logs, ohne PII/Dateiinhalte).
 */
final class ErrorHandler
{
    public static function register(): void
    {
        $local = Env::get('APP_ENV', 'local') === 'local';
        error_reporting(E_ALL);
        ini_set('display_errors', $local ? '1' : '0');
        ini_set('log_errors', '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleException(\Throwable $e): void
    {
        if ($e instanceof HttpException) {
            self::handleHttp($e);
            return;
        }

        $correlationId = Ulid::generate();
        self::log($e, $correlationId);

        if (!headers_sent()) {
            Response::sendSecurityHeaders();
        }
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        $cid = e($correlationId);
        echo "<!doctype html><html lang=\"de\"><meta charset=\"utf-8\">"
            . "<title>500 – Fehler</title>"
            . "<body style=\"font-family:system-ui;margin:3rem;color:#222\">"
            . "<h1>Es ist ein Fehler aufgetreten</h1>"
            . "<p>Bitte versuchen Sie es später erneut.</p>"
            . "<p style=\"color:#888;font-size:.85rem\">Referenz: {$cid}</p>"
            . "</body></html>";
    }

    private static function handleHttp(HttpException $e): void
    {
        if ($e->redirectTo !== null) {
            Response::redirect($e->redirectTo);
            return;
        }
        Response::error($e->statusCode(), $e->getMessage());
    }

    private static function log(\Throwable $e, string $correlationId): void
    {
        $dir = TPB_ROOT . '/private/tpb/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        // Bewusst ohne Request-Body/Parameter (keine PII/Dateiinhalte in Logs, §11).
        $line = sprintf(
            "%s [%s] %s: %s @ %s:%d\n",
            Clock::nowUtcMs(),
            $correlationId,
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        @file_put_contents(
            $dir . '/app-' . gmdate('Y-m-d') . '.log',
            $line,
            FILE_APPEND | LOCK_EX
        );
    }
}
