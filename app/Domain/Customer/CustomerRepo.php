<?php
declare(strict_types=1);

namespace Tpb\Domain\Customer;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;

/**
 * Kundenstamm (§5.4). Anlage aus der Angebotsanfrage; E-Mail dient als
 * Wiedererkennung (kein Zwangs-Login für den MVP-Anfrageweg).
 */
final class CustomerRepo
{
    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $row = Db::run('SELECT * FROM customers WHERE id = ? LIMIT 1', [$id])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        $row = Db::run('SELECT * FROM customers WHERE email = ? ORDER BY id DESC LIMIT 1', [$email])->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Legt einen Kunden an oder aktualisiert Kontakt-/Adressdaten eines bestehenden
     * (Match über E-Mail). Gibt id + public_id.
     *
     * @param array<string,mixed> $d
     * @return array{id:int,public_id:string}
     */
    public static function upsert(array $d): array
    {
        $now = Clock::nowUtcSeconds();
        $email = mb_substr(trim((string) ($d['email'] ?? '')), 0, 190);
        $existing = self::findByEmail($email);

        $fields = [
            'type'             => in_array($d['type'] ?? 'private', ['private', 'business'], true) ? $d['type'] : 'private',
            'company_name'     => self::s($d['company_name'] ?? null, 160),
            'first_name'       => self::s($d['first_name'] ?? null, 80) ?? '',
            'last_name'        => self::s($d['last_name'] ?? null, 80) ?? '',
            'email'            => $email,
            'phone'            => self::s($d['phone'] ?? null, 40),
            'lang'             => mb_substr((string) ($d['lang'] ?? 'de'), 0, 2),
            'billing_street'   => self::s($d['billing_street'] ?? null, 160),
            'billing_zip'      => self::s($d['billing_zip'] ?? null, 16),
            'billing_city'     => self::s($d['billing_city'] ?? null, 80),
            'billing_country'  => self::country($d['billing_country'] ?? null),
            'delivery_street'  => self::s($d['delivery_street'] ?? null, 160),
            'delivery_zip'     => self::s($d['delivery_zip'] ?? null, 16),
            'delivery_city'    => self::s($d['delivery_city'] ?? null, 80),
            'delivery_country' => self::country($d['delivery_country'] ?? null),
        ];

        if ($existing !== null) {
            $id = (int) $existing['id'];
            Db::run(
                'UPDATE customers SET type=?, company_name=?, first_name=?, last_name=?, phone=?, lang=?,
                    billing_street=?, billing_zip=?, billing_city=?, billing_country=?,
                    delivery_street=?, delivery_zip=?, delivery_city=?, delivery_country=?, updated_at=?
                 WHERE id=?',
                [
                    $fields['type'], $fields['company_name'], $fields['first_name'], $fields['last_name'],
                    $fields['phone'], $fields['lang'], $fields['billing_street'], $fields['billing_zip'],
                    $fields['billing_city'], $fields['billing_country'], $fields['delivery_street'],
                    $fields['delivery_zip'], $fields['delivery_city'], $fields['delivery_country'], $now, $id,
                ]
            );
            return ['id' => $id, 'public_id' => (string) $existing['public_id']];
        }

        $publicId = Ulid::generate();
        Db::run(
            'INSERT INTO customers
                (public_id, type, company_name, first_name, last_name, email, phone, lang,
                 billing_street, billing_zip, billing_city, billing_country,
                 delivery_street, delivery_zip, delivery_city, delivery_country, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $publicId, $fields['type'], $fields['company_name'], $fields['first_name'], $fields['last_name'],
                $email, $fields['phone'], $fields['lang'], $fields['billing_street'], $fields['billing_zip'],
                $fields['billing_city'], $fields['billing_country'], $fields['delivery_street'],
                $fields['delivery_zip'], $fields['delivery_city'], $fields['delivery_country'], $now, $now,
            ]
        );
        return ['id' => (int) Db::pdo()->lastInsertId(), 'public_id' => $publicId];
    }

    private static function s(mixed $v, int $max): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $t = trim($v);
        return $t === '' ? null : mb_substr($t, 0, $max);
    }

    private static function country(mixed $v): ?string
    {
        if (!is_string($v) || strlen(trim($v)) !== 2) {
            return null;
        }
        return strtoupper(trim($v));
    }
}
