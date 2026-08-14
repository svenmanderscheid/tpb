<?php
declare(strict_types=1);

namespace Tpb\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tpb\Domain\Finance\Amount;

final class AmountTest extends TestCase
{
    #[DataProvider('centsCases')]
    public function testToCents(string $input, int $expected): void
    {
        self::assertSame($expected, Amount::toCents($input));
    }

    /** @return array<string,array{0:string,1:int}> */
    public static function centsCases(): array
    {
        return [
            'deutsch tausender' => ['1.234,56', 123456],
            'englisch'          => ['1234.56', 123456],
            'englisch tausender' => ['1,234.56', 123456],
            'eine nachkomma'    => ['1234,5', 123450],
            'ganzzahl'          => ['50', 5000],
            'mit euro'          => ['12,00 €', 1200],
        ];
    }

    #[DataProvider('invalidCents')]
    public function testToCentsRejects(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Amount::toCents($input);
    }

    /** @return array<string,array{0:string}> */
    public static function invalidCents(): array
    {
        return ['null' => ['0'], 'negativ' => ['-5'], 'leer' => [''], 'text' => ['abc']];
    }

    #[DataProvider('bpsCases')]
    public function testPercentToBps(string $input, int $expected): void
    {
        self::assertSame($expected, Amount::percentToBps($input));
    }

    /** @return array<string,array{0:string,1:int}> */
    public static function bpsCases(): array
    {
        return [
            'fifty'   => ['50', 5000],
            'drittel' => ['33,33', 3333],
            'voll'    => ['100', 10000],
            'null'    => ['0', 0],
        ];
    }

    public function testPercentOutOfRangeRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Amount::percentToBps('150');
    }
}
