<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * CSRF-Token pro Session; Hidden-Field + X-CSRF-Token-Header (§4).
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Wirft 419 bei fehlendem/falschem Token. */
    public static function check(): void
    {
        $sent = Request::post('_csrf') ?? Request::header('X-CSRF-Token');
        $expected = $_SESSION[self::KEY] ?? '';
        if (!is_string($sent) || !is_string($expected) || $expected === '' || !hash_equals($expected, $sent)) {
            throw new HttpException(419, 'Ungültiges oder fehlendes CSRF-Token');
        }
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }
}
