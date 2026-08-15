<?php
declare(strict_types=1);

namespace Tpb\Domain\Pricing;

/**
 * Ergebnis der Preisberechnung (§6) – vollständige Aufschlüsselung, alle Werte
 * in Cents/Basispunkten, kanonisierbar (calc_hash).
 */
final class Breakdown
{
    /**
     * @param array<int,array<string,mixed>> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $setupsCents,
        public readonly int $filePrepCents,
        public readonly int $subtotalCents,
        public readonly int $expressCents,
        public readonly int $shippingCents,
        public readonly int $totalCents,
        public readonly int $floorCents,
        public readonly bool $belowMinOrder,
        public readonly bool $belowFloor,
        public readonly string $calcHash,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'items'           => $this->items,
            'setups_cents'    => $this->setupsCents,
            'fileprep_cents'  => $this->filePrepCents,
            'subtotal_cents'  => $this->subtotalCents,
            'express_cents'   => $this->expressCents,
            'shipping_cents'  => $this->shippingCents,
            'total_cents'     => $this->totalCents,
            'floor_cents'     => $this->floorCents,
            'below_min_order' => $this->belowMinOrder,
            'below_floor'     => $this->belowFloor,
            'calc_hash'       => $this->calcHash,
        ];
    }
}
