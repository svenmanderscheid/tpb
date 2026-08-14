<?php
declare(strict_types=1);

namespace Tpb\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tpb\Core\Money;

final class MoneyTest extends TestCase
{
    #[DataProvider('bpCases')]
    public function testBpRoundHalfUp(int $cents, int $bps, int $expected): void
    {
        self::assertSame($expected, Money::bp($cents, $bps));
    }

    /** @return array<string,array{0:int,1:int,2:int}> */
    public static function bpCases(): array
    {
        return [
            '25% von 1000'        => [1000, 2500, 250],
            '19% von 10000'       => [10000, 1900, 1900],
            'half-up 1250*10%'    => [1250, 1000, 125],
            'half-up rundet auf'  => [101, 5000, 51],   // 50,5 -> 51
            'exakt null bps'      => [9999, 0, 0],
            'negativer Betrag'    => [-1000, 2500, -250],
            'negativer bps'       => [1000, -2500, -250],
        ];
    }

    public function testCeilDiv(): void
    {
        self::assertSame(4, Money::ceilDiv(10, 3));
        self::assertSame(5, Money::ceilDiv(10, 2));
        self::assertSame(1, Money::ceilDiv(1, 10000));
    }

    public function testCeilDivByZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::ceilDiv(10, 0);
    }

    public function testFormat(): void
    {
        self::assertSame('123,45 EUR', Money::format(12345));
        self::assertSame('1.000,00 EUR', Money::format(100000));
        self::assertSame('-5,09 EUR', Money::format(-509));
    }
}
