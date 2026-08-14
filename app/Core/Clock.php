<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Zeit: DB speichert UTC, Anzeige über toLux (§3.3).
 */
final class Clock
{
    public static function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /** DATETIME(3) – mit Millisekunden. */
    public static function nowUtcMs(): string
    {
        return self::nowUtc()->format('Y-m-d H:i:s.v');
    }

    /** DATETIME – Sekundengenauigkeit. */
    public static function nowUtcSeconds(): string
    {
        return self::nowUtc()->format('Y-m-d H:i:s');
    }

    public static function toLux(\DateTimeInterface $dt): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($dt)
            ->setTimezone(new \DateTimeZone('Europe/Luxembourg'));
    }
}
