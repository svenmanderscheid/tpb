<?php
declare(strict_types=1);

namespace Tpb\Domain\Pricing;

/**
 * Value Object einer Kostenversion für die interne Untergrenze (§6).
 * cost_items werden per "refType:refId:paramKey" indiziert.
 *
 * Zuordnungsannahme (dokumentiert, siehe docs/OFFENE-FRAGEN.md):
 *  - BLANK_CENTS/MATERIAL_CENTS: ref_type=variant (Fallback product)
 *  - SETUP_MIN/UNIT_MIN/MACHINE_MIN: ref_type=technique (Fallback product)
 */
final class CostVersion
{
    /**
     * @param array<string,int> $items  Schlüssel "refType:refId:paramKey" => value_int
     */
    public function __construct(
        public readonly int $version,
        public readonly int $laborRateCentsH,
        public readonly int $machineRateCentsH,
        public readonly int $scrapBps,
        public readonly int $targetMarginBps,
        private readonly array $items,
    ) {
    }

    public function item(string $refType, int $refId, string $paramKey): ?int
    {
        return $this->items["{$refType}:{$refId}:{$paramKey}"] ?? null;
    }

    /** Erster Treffer über eine Liste von [refType, refId]-Kandidaten. */
    public function itemAny(array $candidates, string $paramKey, int $default = 0): int
    {
        foreach ($candidates as [$refType, $refId]) {
            if ($refId === null) {
                continue;
            }
            $v = $this->item($refType, (int) $refId, $paramKey);
            if ($v !== null) {
                return $v;
            }
        }
        return $default;
    }
}
