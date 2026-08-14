<?php
declare(strict_types=1);

namespace Tpb\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Ulid;

final class UlidTest extends TestCase
{
    public function testLengthAndCharset(): void
    {
        $ulid = Ulid::generate();
        self::assertSame(26, strlen($ulid));
        self::assertTrue(Ulid::isValid($ulid));
        self::assertSame(26, strspn($ulid, '0123456789ABCDEFGHJKMNPQRSTVWXYZ'));
    }

    public function testMonotonicWithinSameMillisecond(): void
    {
        $ms = 1_700_000_000_000;
        $a = Ulid::generate($ms);
        $b = Ulid::generate($ms);
        $c = Ulid::generate($ms);
        // Gleiche Zeitkomponente, aber streng monoton steigend.
        self::assertSame(substr($a, 0, 10), substr($b, 0, 10));
        self::assertTrue($a < $b, "$a should sort before $b");
        self::assertTrue($b < $c, "$b should sort before $c");
    }

    public function testTimeComponentSortsChronologically(): void
    {
        $early = Ulid::generate(1_700_000_000_000);
        $later = Ulid::generate(1_700_000_001_000);
        self::assertTrue($early < $later);
    }

    public function testUniqueness(): void
    {
        $set = [];
        for ($i = 0; $i < 1000; $i++) {
            $set[Ulid::generate()] = true;
        }
        self::assertCount(1000, $set);
    }

    public function testInvalidRejected(): void
    {
        self::assertFalse(Ulid::isValid('too-short'));
        self::assertFalse(Ulid::isValid(str_repeat('I', 26))); // I ist nicht in Crockford-Base32
    }
}
