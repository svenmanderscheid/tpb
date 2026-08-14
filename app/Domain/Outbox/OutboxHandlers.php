<?php
declare(strict_types=1);

namespace Tpb\Domain\Outbox;

/**
 * Handler-Registry (§10). Jeder Handler ist idempotent. In M0 ist der
 * Mail-Kanal implementiert (file-Driver); weitere Handler folgen je Meilenstein.
 */
final class OutboxHandlers
{
    /**
     * @param array<string,mixed> $payload
     */
    public static function dispatch(string $eventType, array $payload): void
    {
        if (str_starts_with($eventType, 'mail.')) {
            Mailer::sendFromPayload($payload);
            return;
        }

        throw new \RuntimeException("Kein Handler für Event-Typ: {$eventType}");
    }
}
