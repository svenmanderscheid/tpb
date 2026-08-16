<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Capability-Matrix (§4). Serverseitige Prüfung auf jeder Adminroute (§13.1).
 * Menü-Ausblenden ist keine Zugriffskontrolle.
 */
final class Authz
{
    public const ROLES = ['owner', 'admin', 'sales', 'production', 'finance', 'readonly'];

    /** @var array<string,string[]> */
    public const MATRIX = [
        'tpb_manage_pricing'    => ['owner', 'admin'],
        'tpb_view_costs'        => ['owner', 'admin', 'finance'],
        'tpb_manage_quotes'     => ['owner', 'admin', 'sales'],
        'tpb_manage_artwork'    => ['owner', 'admin', 'sales'],
        'tpb_manage_production' => ['owner', 'admin', 'production'],
        'tpb_issue_invoices'    => ['owner', 'finance'],
        'tpb_manage_legal'      => ['owner'],
        'tpb_reprint_labels'    => ['owner', 'admin', 'production'],
        'tpb_view_audit'        => ['owner'],
        'tpb_manage_users'      => ['owner'],
        // Finanzmodul (Abweichung, siehe docs/DECISIONS.md #16)
        'tpb_manage_finance'    => ['owner', 'finance'],
    ];

    public static function roleHas(string $role, string $capability): bool
    {
        if (!array_key_exists($capability, self::MATRIX)) {
            throw new \InvalidArgumentException("Unknown capability: {$capability}");
        }
        return in_array($role, self::MATRIX[$capability], true);
    }

    public static function can(string $capability): bool
    {
        $role = Auth::role();
        return $role !== null && self::roleHas($role, $capability);
    }

    /** Wirft 403, wenn die aktuelle Rolle die Capability nicht hat. */
    public static function require(string $capability): void
    {
        if (!self::can($capability)) {
            throw new HttpException(403, 'Fehlende Berechtigung');
        }
    }
}
