<?php
declare(strict_types=1);

namespace Tpb\Domain\Pricing;

/**
 * Value Object eines veröffentlichten Preisbuchs (§6). Enthält Staffeln und
 * Parameter; keine DB-Zugriffe – die Repos laden alles vorab (§6).
 */
final class PriceBook
{
    /**
     * @param array<int,array{product_id:int,qty_from:int,qty_to:?int,unit_cents:int}> $tiers
     * @param array<string,int> $params
     */
    public function __construct(
        public readonly int $version,
        public readonly string $currency,
        private readonly array $tiers,
        private readonly array $params,
    ) {
    }

    /**
     * Staffel-Stückpreis für Produkt und Gesamtmenge. Fehlender Tier ⇒
     * PricingException (§6.2 – niemals 0 annehmen).
     */
    public function unitTier(int $productId, int $qty): int
    {
        foreach ($this->tiers as $t) {
            if ($t['product_id'] !== $productId) {
                continue;
            }
            $to = $t['qty_to'];
            if ($qty >= $t['qty_from'] && ($to === null || $qty <= $to)) {
                return $t['unit_cents'];
            }
        }
        throw new PricingException("Kein Staffelpreis für Produkt {$productId} bei Menge {$qty}.");
    }

    public function param(string $key): ?int
    {
        return $this->params[$key] ?? null;
    }

    public function paramOr(string $key, int $default = 0): int
    {
        return $this->params[$key] ?? $default;
    }
}
