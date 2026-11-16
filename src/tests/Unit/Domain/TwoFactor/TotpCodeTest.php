<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\TwoFactor;

use App\Domain\TwoFactor\Exceptions\InvalidTwoFactorCodeException;
use App\Domain\TwoFactor\ValueObjects\TotpCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedCodes(): iterable
    {
        yield 'six digits' => ['123456', '123456'];
        yield 'leading zeros' => ['000042', '000042'];
        yield 'grouped with a space' => ['123 456', '123456'];
        yield 'surrounded by blanks' => ["  123456\n", '123456'];
        yield 'tabs and non-breaking spaces' => ["12\t34\u{00A0}56", '123456'];
    }

    #[DataProvider('acceptedCodes')]
    public function test_it_reads_six_digits_ignoring_spaces(string $input, string $expected): void
    {
        $this->assertSame($expected, TotpCode::fromString($input)->value);
        $this->assertSame($expected, TotpCode::tryFrom($input)?->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'five digits' => ['12345'];
        yield 'seven digits' => ['1234567'];
        yield 'letters' => ['12a456'];
        yield 'hyphen' => ['123-456'];
        yield 'arabic-indic digits' => ['١٢٣٤٥٦'];
        yield 'a recovery code' => ['abcde-fghjk'];
    }

    #[DataProvider('rejectedCodes')]
    public function test_it_rejects_anything_else(string $input): void
    {
        $this->assertNull(TotpCode::tryFrom($input));

        $this->expectException(InvalidTwoFactorCodeException::class);
        TotpCode::fromString($input);
    }

    public function test_the_rejection_never_carries_what_was_typed(): void
    {
        try {
            TotpCode::fromString('98765');
            $this->fail('Expected an exception');
        } catch (InvalidTwoFactorCodeException $exception) {
            $this->assertStringNotContainsString('98765', $exception->getMessage());
        }
    }
}
