<?php
declare(strict_types=1);

// Projektwurzel (app/Core/helpers.php -> ../../ = Wurzel)
if (!defined('TPB_ROOT')) {
    define('TPB_ROOT', dirname(__DIR__, 2));
}

if (!function_exists('e')) {
    /**
     * HTML-Escaping für jeden View-Output (§3.12). ENT_QUOTES + UTF-8.
     */
    function e(int|float|string|null $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
