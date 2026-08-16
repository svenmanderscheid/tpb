<?php
declare(strict_types=1);

namespace Tpb\Domain\Status;

/**
 * Zentrale Statusachsen und erlaubte Übergänge (§7). Alles, was hier nicht steht,
 * ist verboten (IllegalTransitionException). Der Anfangszustand steht unter dem
 * Schlüssel '' (kein Vorzustand). Diese Klasse ist die einzige Wahrheit über
 * erlaubte Übergänge; Cache-Spalten (orders.cur_*) sind nur Spiegel von status_events.
 *
 * payment ist abgeleitet (aus payments/allocations, §7) und wird nie manuell gesetzt;
 * die Achse ist hier zur Vollständigkeit hinterlegt, aber im MVP nicht über transition()
 * geschaltet.
 */
final class States
{
    /** @var array<string, array<string, list<string>>> */
    private const MAP = [
        'quote' => [
            ''         => ['DRAFT'],
            'DRAFT'    => ['SENT', 'CANCELLED'],
            'SENT'     => ['ACCEPTED', 'DECLINED', 'EXPIRED', 'CANCELLED'],
            'ACCEPTED' => [],
            'DECLINED' => [],
            'EXPIRED'  => [],
            'CANCELLED' => [],
        ],
        'order' => [
            ''                => ['PENDING_PAYMENT', 'CONFIRMED'],
            'PENDING_PAYMENT' => ['CONFIRMED', 'EXPIRED', 'CANCELLED'],
            'CONFIRMED'       => ['COMPLETED', 'CANCELLED'],
            'COMPLETED'       => [],
            'CANCELLED'       => [],
            'EXPIRED'         => [],
        ],
        'payment' => [
            ''               => ['NOT_DUE', 'UNPAID'],
            'NOT_DUE'        => ['UNPAID'],
            'UNPAID'         => ['PARTIALLY_PAID', 'PAID'],
            'PARTIALLY_PAID' => ['PAID'],
            'PAID'           => ['REFUNDED'],
            'REFUNDED'       => [],
        ],
        'artwork' => [
            ''                  => ['MISSING', 'LOCKED'],
            'MISSING'           => ['UPLOADED'],
            'UPLOADED'          => ['PREPRESS_REVIEW'],
            'PREPRESS_REVIEW'   => ['PROOF_SENT'],
            'PROOF_SENT'        => ['CHANGES_REQUESTED', 'APPROVED'],
            'CHANGES_REQUESTED' => ['PROOF_SENT'],
            'APPROVED'          => ['LOCKED'],
            'LOCKED'            => [],
        ],
        'production' => [
            ''             => ['BLOCKED'],
            'BLOCKED'      => ['READY'],
            'READY'        => ['IN_PROGRESS'],
            'IN_PROGRESS'  => ['QUALITY_CHECK', 'REWORK', 'SCRAPPED'],
            'QUALITY_CHECK' => ['DONE', 'REWORK'],
            'REWORK'       => ['IN_PROGRESS'],
            'DONE'         => [],
            'SCRAPPED'     => [],
        ],
        'fulfillment' => [
            ''                 => ['UNFULFILLED'],
            'UNFULFILLED'      => ['PACKING'],
            'PACKING'          => ['READY_FOR_PICKUP', 'READY_TO_SHIP'],
            'READY_FOR_PICKUP' => ['COLLECTED'],
            'READY_TO_SHIP'    => ['SHIPPED'],
            'SHIPPED'          => ['DELIVERED'],
            'COLLECTED'        => [],
            'DELIVERED'        => [],
        ],
        'invoice' => [
            ''                   => ['NONE', 'DRAFT'],
            'NONE'               => ['DRAFT'],
            'DRAFT'              => ['ISSUED'],
            'ISSUED'             => ['SENT', 'PARTIALLY_CREDITED', 'FULLY_CREDITED'],
            'SENT'               => ['PARTIALLY_CREDITED', 'FULLY_CREDITED'],
            'PARTIALLY_CREDITED' => ['FULLY_CREDITED'],
            'FULLY_CREDITED'     => [],
        ],
    ];

    public static function allowed(string $axis, ?string $from, string $to): bool
    {
        $key = $from ?? '';
        return in_array($to, self::MAP[$axis][$key] ?? [], true);
    }

    /** @throws IllegalTransitionException */
    public static function assert(string $axis, ?string $from, string $to): void
    {
        if (!isset(self::MAP[$axis])) {
            throw new IllegalTransitionException("Unbekannte Statusachse: {$axis}");
        }
        if (!self::allowed($axis, $from, $to)) {
            $fromLabel = $from ?? '(Start)';
            throw new IllegalTransitionException("Verbotener Übergang {$axis}: {$fromLabel} → {$to}");
        }
    }

    /** @return list<string> Terminale Zustände einer Achse (keine ausgehenden Übergänge). */
    public static function terminals(string $axis): array
    {
        $out = [];
        foreach (self::MAP[$axis] ?? [] as $state => $targets) {
            if ($state !== '' && $targets === []) {
                $out[] = $state;
            }
        }
        return $out;
    }
}
