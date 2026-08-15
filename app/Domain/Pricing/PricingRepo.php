<?php
declare(strict_types=1);

namespace Tpb\Domain\Pricing;

use Tpb\Core\Db;

/**
 * Lädt veröffentlichte Preisbücher/Kostenversionen in Value Objects für die
 * Engine (§6: Repos laden vorab, Engine rechnet ohne DB-Zugriff).
 */
final class PricingRepo
{
    /** Aktuell veröffentlichtes Preisbuch (höchste Version) oder null. */
    public static function activePriceBook(): ?PriceBook
    {
        $book = Db::run(
            "SELECT id, version, currency FROM price_books WHERE status = 'published' ORDER BY version DESC LIMIT 1"
        )->fetch();
        if (!$book) {
            return null;
        }
        $bookId = (int) $book['id'];

        $tiers = [];
        foreach (Db::run('SELECT product_id, qty_from, qty_to, unit_cents FROM price_tiers WHERE price_book_id = ?', [$bookId])->fetchAll() as $t) {
            $tiers[] = [
                'product_id' => (int) $t['product_id'],
                'qty_from'   => (int) $t['qty_from'],
                'qty_to'     => $t['qty_to'] === null ? null : (int) $t['qty_to'],
                'unit_cents' => (int) $t['unit_cents'],
            ];
        }

        $params = [];
        foreach (Db::run('SELECT param_key, value_int FROM price_params WHERE price_book_id = ?', [$bookId])->fetchAll() as $p) {
            $params[(string) $p['param_key']] = (int) $p['value_int'];
        }

        return new PriceBook((int) $book['version'], (string) $book['currency'], $tiers, $params);
    }

    /** Aktuell veröffentlichte Kostenversion (höchste Version) oder null. */
    public static function activeCostVersion(): ?CostVersion
    {
        $cv = Db::run(
            "SELECT id, version, labor_rate_cents_h, machine_rate_cents_h, scrap_bps, target_margin_bps
             FROM cost_versions WHERE status = 'published' ORDER BY version DESC LIMIT 1"
        )->fetch();
        if (!$cv) {
            return null;
        }
        $items = [];
        foreach (Db::run('SELECT ref_type, ref_id, param_key, value_int FROM cost_items WHERE cost_version_id = ?', [(int) $cv['id']])->fetchAll() as $it) {
            $items["{$it['ref_type']}:{$it['ref_id']}:{$it['param_key']}"] = (int) $it['value_int'];
        }

        return new CostVersion(
            (int) $cv['version'],
            (int) $cv['labor_rate_cents_h'],
            (int) $cv['machine_rate_cents_h'],
            (int) $cv['scrap_bps'],
            (int) $cv['target_margin_bps'],
            $items
        );
    }
}
