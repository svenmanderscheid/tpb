<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * View-Rendering mit Layout-Wrapping (§4). e() global verfügbar (helpers.php).
 */
final class View
{
    private static string $base = '';

    public static function base(string $dir): void
    {
        self::$base = rtrim($dir, '/');
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layout/admin'): string
    {
        $content = self::capture(self::path($template), $data);
        if ($layout !== null) {
            $layoutFile = self::path($layout);
            if (is_file($layoutFile)) {
                $content = self::capture($layoutFile, array_merge($data, ['content' => $content]));
            }
        }
        return $content;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function capture(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$file}");
        }
        $render = static function () use ($file, $data): void {
            extract($data, EXTR_SKIP);
            require $file;
        };
        ob_start();
        try {
            $render();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    private static function path(string $template): string
    {
        return self::$base . '/' . $template . '.php';
    }
}
