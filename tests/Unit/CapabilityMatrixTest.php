<?php
declare(strict_types=1);

namespace Tpb\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Authz;

final class CapabilityMatrixTest extends TestCase
{
    /**
     * Vollständige Matrix: erwartete erlaubte Rollen je Capability (§4).
     * @return array<string,string[]>
     */
    private static function expected(): array
    {
        return [
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
            'tpb_manage_finance'    => ['owner', 'finance'],
        ];
    }

    public function testEveryRoleAgainstEveryCapability(): void
    {
        foreach (self::expected() as $capability => $allowedRoles) {
            foreach (Authz::ROLES as $role) {
                $shouldHave = in_array($role, $allowedRoles, true);
                self::assertSame(
                    $shouldHave,
                    Authz::roleHas($role, $capability),
                    "Rolle {$role} / Capability {$capability}"
                );
            }
        }
    }

    public function testReadonlyHasNoCapabilities(): void
    {
        foreach (array_keys(self::expected()) as $capability) {
            self::assertFalse(Authz::roleHas('readonly', $capability));
        }
    }

    public function testMatrixMatchesImplementation(): void
    {
        // Verhindert stilles Auseinanderdriften von Test-Erwartung und Konstante.
        self::assertSame(self::expected(), Authz::MATRIX);
    }

    public function testUnknownCapabilityThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Authz::roleHas('owner', 'tpb_does_not_exist');
    }
}
