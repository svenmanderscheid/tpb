<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Singleton-PDO über den Db-Wrapper. Nur Prepared Statements (§3.10).
 */
final class Db
{
    private static ?\PDO $pdo = null;
    private static int $txDepth = 0;

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $name = Env::require('DB_NAME');
            $user = Env::get('DB_USER', 'root') ?? 'root';
            $pass = Env::get('DB_PASS', '') ?? '';
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            self::$pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /**
     * @param array<int|string,mixed> $params
     */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Transaktion; gibt den Rückgabewert von $fn durch, Rollback bei Exception.
     * Verschachtelte Transaktionen sind verboten (§4).
     */
    public static function tx(callable $fn): mixed
    {
        if (self::$txDepth > 0) {
            throw new \RuntimeException('Nested transactions are forbidden');
        }
        $pdo = self::pdo();
        self::$txDepth = 1;
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            self::$txDepth = 0;
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::$txDepth = 0;
            throw $e;
        }
    }

    /** Setzt die Verbindung zurück (Tests). */
    public static function reset(): void
    {
        self::$pdo = null;
        self::$txDepth = 0;
    }
}
