<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

/**
 * Kleine Flash-Message-Hilfe über die Session (einmalige Meldung nach Redirect).
 */
trait FlashTrait
{
    private function flash(string $type, string $text): void
    {
        $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    }

    /** @return array{type:string,text:string}|null */
    private function takeFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($f) ? $f : null;
    }
}
