<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Kapselt den eingehenden HTTP-Request.
 */
final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        return rawurldecode($path);
    }

    public static function post(string $key, ?string $default = null): ?string
    {
        $v = $_POST[$key] ?? null;
        return is_string($v) ? $v : $default;
    }

    public static function query(string $key, ?string $default = null): ?string
    {
        $v = $_GET[$key] ?? null;
        return is_string($v) ? $v : $default;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v = $_SERVER[$key] ?? null;
        return is_string($v) ? $v : null;
    }

    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) ? $ip : '0.0.0.0';
    }

    public static function isJson(): bool
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        return is_string($ct) && str_contains(strtolower($ct), 'application/json');
    }
}
