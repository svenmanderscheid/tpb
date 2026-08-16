<?php
declare(strict_types=1);

namespace Tpb\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tpb\Domain\Auth\Totp;

/**
 * TOTP gegen die RFC-6238-Testvektoren (SHA-1). Secret = ASCII "12345678901234567890"
 * als Base32.
 */
final class TotpTest extends TestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testRfc6238Vectors(): void
    {
        self::assertSame('287082', Totp::code(self::SECRET, 59));
        self::assertSame('081804', Totp::code(self::SECRET, 1111111109));
        self::assertSame('050471', Totp::code(self::SECRET, 1111111111));
        self::assertSame('005924', Totp::code(self::SECRET, 1234567890));
        self::assertSame('279037', Totp::code(self::SECRET, 2000000000));
    }

    public function testVerifyAcceptsCorrectAndRejectsWrong(): void
    {
        self::assertTrue(Totp::verify(self::SECRET, '287082', 1, 59));
        self::assertFalse(Totp::verify(self::SECRET, '000000', 1, 59));
        self::assertFalse(Totp::verify(self::SECRET, 'abc', 1, 59));
    }

    public function testVerifyWindowToleratesDrift(): void
    {
        // Code aus dem vorherigen 30-Sekunden-Schritt wird im Fenster ±1 akzeptiert.
        $prev = Totp::code(self::SECRET, 59 - 30);
        self::assertTrue(Totp::verify(self::SECRET, $prev, 1, 59));
    }

    public function testGeneratedSecretRoundTrips(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::code($secret, 100);
        self::assertSame(6, strlen($code));
        self::assertTrue(Totp::verify($secret, $code, 1, 100));
    }
}
