<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Zentrale Ausgabe. JSON-Antworten immer über json() (§3.12).
 */
final class Response
{
    private static bool $securitySent = false;

    /**
     * Security-Header global (§11): Frame-Deny, nosniff, Referrer, CSP.
     */
    public static function sendSecurityHeaders(): void
    {
        if (self::$securitySent || headers_sent()) {
            return;
        }
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header_remove('X-Powered-By');
        self::$securitySent = true;
    }

    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function redirect(string $to, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $to);
    }

    public static function error(int $status, string $message = ''): void
    {
        self::sendSecurityHeaders();
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        $title = self::statusText($status);
        $safe = e($message !== '' ? $message : $title);
        echo "<!doctype html><html lang=\"de\"><meta charset=\"utf-8\">"
            . "<title>{$status} {$title}</title>"
            . "<body style=\"font-family:system-ui;margin:3rem;color:#222\">"
            . "<h1>{$status} – {$title}</h1><p>{$safe}</p></body></html>";
    }

    private static function statusText(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Session abgelaufen',
            429 => 'Too Many Requests',
            default => 'Fehler',
        };
    }
}
