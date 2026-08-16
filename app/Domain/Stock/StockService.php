<?php
declare(strict_types=1);

namespace Tpb\Domain\Stock;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Outbox\MailTemplates;
use Tpb\Domain\Outbox\Outbox;

/**
 * Bestandsführung (DECISIONS #31). Reservierung beim Checkout (kein Oversell durch
 * FOR UPDATE), Abbuchung erst bei bezahlter Bestellung, Freigabe bei Abbruch/Ablauf.
 * Jede physische Änderung schreibt eine Bewegung + Audit; Meldebestand-Alarme (Default 5,
 * dann 2, dann leer) gehen einmalig je Stufe über die Outbox raus.
 */
final class StockService
{
    /**
     * Reserviert Mengen je Variante für einen Auftrag. MUSS in einer aktiven Transaktion
     * laufen (Checkout). Sperrt die Variantenzeile (FOR UPDATE) und weist bei zu wenig
     * Verfügbarkeit ab.
     *
     * @param array<int,int> $variantQtys variant_id => qty
     * @throws StockException
     */
    public static function reserveForOrder(int $orderId, array $variantQtys, ?int $actorUserId): void
    {
        $now = Clock::nowUtcSeconds();
        foreach ($variantQtys as $variantId => $qty) {
            if ($qty <= 0) {
                continue;
            }
            $v = Db::run('SELECT id, sku, stock_qty, reserve_qty FROM product_variants WHERE id = ? FOR UPDATE', [(int) $variantId])->fetch();
            if ($v === false) {
                throw new StockException('Variante nicht gefunden.');
            }
            $available = (int) $v['stock_qty'] - (int) $v['reserve_qty'] - StockRepo::reservedActive((int) $variantId, $orderId);
            if ($qty > $available) {
                throw new StockException('Nicht genügend Bestand für ' . (string) $v['sku'] . ' (nur noch ' . max(0, $available) . ' verfügbar).');
            }
            Db::run(
                'INSERT INTO stock_reservations (order_id, variant_id, qty, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$orderId, (int) $variantId, $qty, 'active', $now, $now]
            );
        }
    }

    /** Bucht bezahlte Reservierungen ab (Abbuchung erst bei erfolgreicher Zahlung). Idempotent. */
    public static function consumeForOrder(int $orderId, ?int $actorUserId): void
    {
        Db::tx(function () use ($orderId, $actorUserId): void {
            $reservations = Db::run("SELECT * FROM stock_reservations WHERE order_id = ? AND status = 'active'", [$orderId])->fetchAll();
            $touched = [];
            foreach ($reservations as $r) {
                self::applyMovement((int) $r['variant_id'], -(int) $r['qty'], 'sale', 'order', $orderId, null, $actorUserId);
                Db::run("UPDATE stock_reservations SET status = 'consumed', updated_at = ? WHERE id = ?", [Clock::nowUtcSeconds(), (int) $r['id']]);
                $touched[(int) $r['variant_id']] = true;
            }
            foreach (array_keys($touched) as $variantId) {
                self::checkAlerts((int) $variantId, $actorUserId);
            }
        });
    }

    /** Gibt aktive Reservierungen frei (Abbruch/Ablauf – kein Bestandsbezug). Idempotent. */
    public static function releaseForOrder(int $orderId): void
    {
        Db::run("UPDATE stock_reservations SET status = 'released', updated_at = ? WHERE order_id = ? AND status = 'active'", [Clock::nowUtcSeconds(), $orderId]);
    }

    /** Setzt den physischen Bestand auf einen Zielwert (manuelle Korrektur). */
    public static function adjustTo(int $variantId, int $targetQty, ?int $actorUserId, ?string $note = null): void
    {
        Db::tx(function () use ($variantId, $targetQty, $actorUserId, $note): void {
            $cur = (int) Db::run('SELECT stock_qty FROM product_variants WHERE id = ? FOR UPDATE', [$variantId])->fetchColumn();
            $delta = $targetQty - $cur;
            if ($delta !== 0) {
                self::applyMovement($variantId, $delta, 'adjustment', null, null, $note, $actorUserId);
            }
            self::checkAlerts($variantId, $actorUserId);
        });
    }

    /** Wareneingang: erhöht den Bestand (manuell, später aus Lieferschein). */
    public static function receive(int $variantId, int $delta, ?int $actorUserId, ?string $note = null): void
    {
        if ($delta <= 0) {
            throw new StockException('Wareneingang muss größer als 0 sein.');
        }
        Db::tx(function () use ($variantId, $delta, $actorUserId, $note): void {
            Db::run('SELECT id FROM product_variants WHERE id = ? FOR UPDATE', [$variantId]);
            self::applyMovement($variantId, $delta, 'receipt', null, null, $note, $actorUserId);
            self::checkAlerts($variantId, $actorUserId);
        });
    }

    public static function setReserve(int $variantId, int $reserveQty, ?int $actorUserId): void
    {
        Db::run('UPDATE product_variants SET reserve_qty = ?, updated_at = ? WHERE id = ?', [max(0, $reserveQty), Clock::nowUtcSeconds(), $variantId]);
        Audit::log('variant', (string) $variantId, 'stock.reserve_set', ['actor_user_id' => $actorUserId, 'metadata' => ['reserve' => max(0, $reserveQty)]]);
    }

    public static function setReorderThreshold(int $variantId, int $threshold, ?int $actorUserId): void
    {
        Db::run('UPDATE product_variants SET reorder_threshold = ?, updated_at = ? WHERE id = ?', [max(0, $threshold), Clock::nowUtcSeconds(), $variantId]);
        Audit::log('variant', (string) $variantId, 'stock.reorder_set', ['actor_user_id' => $actorUserId, 'metadata' => ['threshold' => max(0, $threshold)]]);
    }

    /** Setzt den Meldebestand für ALLE Varianten gleichzeitig. */
    public static function bulkSetReorderThreshold(int $threshold, ?int $actorUserId): int
    {
        $n = Db::run('UPDATE product_variants SET reorder_threshold = ?, updated_at = ?', [max(0, $threshold), Clock::nowUtcSeconds()])->rowCount();
        Audit::log('variant', 'all', 'stock.reorder_set_all', ['actor_user_id' => $actorUserId, 'metadata' => ['threshold' => max(0, $threshold), 'count' => $n]]);
        return $n;
    }

    /** Schreibt eine Bestandsbewegung und aktualisiert den Cache stock_qty. Innerhalb einer Transaktion. */
    private static function applyMovement(int $variantId, int $delta, string $reason, ?string $refType, ?int $refId, ?string $note, ?int $actorUserId): void
    {
        Db::run('UPDATE product_variants SET stock_qty = stock_qty + ?, updated_at = ? WHERE id = ?', [$delta, Clock::nowUtcSeconds(), $variantId]);
        $balance = (int) Db::run('SELECT stock_qty FROM product_variants WHERE id = ? LIMIT 1', [$variantId])->fetchColumn();
        Db::run(
            'INSERT INTO stock_movements (variant_id, delta, reason, balance_after, ref_type, ref_id, note, actor_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$variantId, $delta, $reason, $balance, $refType, $refId, $note, $actorUserId, Clock::nowUtcSeconds()]
        );
        Audit::log('variant', (string) $variantId, 'stock.movement', ['actor_user_id' => $actorUserId, 'metadata' => ['delta' => $delta, 'reason' => $reason, 'balance' => $balance]]);
    }

    /**
     * Meldebestand-Alarme (Owner-Wunsch: unter Meldebestand, dann 2, dann leer). Einmalig
     * je Stufe (stock_alerts UNIQUE); steigt der Bestand wieder, wird die Stufe zurückgesetzt.
     */
    private static function checkAlerts(int $variantId, ?int $actorUserId): void
    {
        $v = Db::run('SELECT sku, stock_qty, reorder_threshold, product_id FROM product_variants WHERE id = ? LIMIT 1', [$variantId])->fetch();
        if ($v === false) {
            return;
        }
        $stock = (int) $v['stock_qty'];
        $threshold = (int) $v['reorder_threshold'];
        $applicable = [
            'reorder'  => $stock <= $threshold,
            'critical' => $stock <= 2,
            'empty'    => $stock <= 0,
        ];

        $already = Db::run('SELECT level FROM stock_alerts WHERE variant_id = ?', [$variantId])->fetchAll(\PDO::FETCH_COLUMN);
        $already = array_flip(array_map('strval', $already));

        foreach ($applicable as $level => $isOn) {
            if ($isOn && !isset($already[$level])) {
                $outboxId = self::enqueueAlert($variantId, (string) $v['sku'], (int) $v['product_id'], $level, $stock);
                Db::run('INSERT INTO stock_alerts (variant_id, level, sent_at, outbox_id) VALUES (?, ?, ?, ?)', [$variantId, $level, Clock::nowUtcSeconds(), $outboxId]);
            } elseif (!$isOn && isset($already[$level])) {
                Db::run('DELETE FROM stock_alerts WHERE variant_id = ? AND level = ?', [$variantId, $level]);
            }
        }
    }

    private static function enqueueAlert(int $variantId, string $sku, int $productId, string $level, int $stock): int
    {
        $product = (string) Db::run('SELECT name FROM products WHERE id = ? LIMIT 1', [$productId])->fetchColumn();
        $to = self::alertEmail();
        $mail = ['to_email' => $to, 'sku' => $sku, 'product' => $product, 'level' => $level, 'stock' => $stock];
        return Outbox::enqueue('mail.stock_low', array_merge($mail, MailTemplates::stockLow($mail)), 'stock_' . $level . '_' . $variantId . '_' . $stock);
    }

    private static function alertEmail(): string
    {
        $row = Db::run("SELECT email FROM users WHERE role = 'owner' AND status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($row !== false) {
            return (string) $row;
        }
        return (string) (Env::get('MAIL_FROM', 'owner@tpb.local') ?? 'owner@tpb.local');
    }
}
