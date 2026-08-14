<?php
declare(strict_types=1);

namespace Tpb\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Canonical;

final class CanonicalTest extends TestCase
{
    public function testKeysSortedRecursively(): void
    {
        $a = ['b' => 1, 'a' => ['z' => 2, 'y' => 3]];
        $b = ['a' => ['y' => 3, 'z' => 2], 'b' => 1];
        self::assertSame(Canonical::json($a), Canonical::json($b));
    }

    public function testStableHashForEquivalentData(): void
    {
        $a = ['total' => 1000, 'items' => [['sku' => 'X', 'qty' => 2]]];
        $b = ['items' => [['qty' => 2, 'sku' => 'X']], 'total' => 1000];
        self::assertSame(Canonical::hash($a), Canonical::hash($b));
    }

    public function testUnescapedUnicodeAndSlashes(): void
    {
        $json = Canonical::json(['url' => 'https://tpb.local/ä']);
        self::assertStringContainsString('https://tpb.local/ä', $json);
        self::assertStringNotContainsString('\\/', $json);
        self::assertStringNotContainsString('\\u00e4', $json);
    }

    public function testListOrderPreserved(): void
    {
        $json = Canonical::json(['list' => [3, 1, 2]]);
        self::assertSame('{"list":[3,1,2]}', $json);
    }

    public function testFloatsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Canonical::json(['amount' => 12.5]);
    }
}
