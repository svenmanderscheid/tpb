<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Trägt einen HTTP-Statuscode; vom ErrorHandler in eine saubere Antwort übersetzt.
 */
final class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        public readonly ?string $redirectTo = null,
    ) {
        parent::__construct($message === '' ? "HTTP {$statusCode}" : $message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
