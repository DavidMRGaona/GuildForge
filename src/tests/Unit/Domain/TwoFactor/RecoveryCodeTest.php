<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\TwoFactor;

use App\Domain\TwoFactor\Exceptions\InvalidTwoFactorCodeException;
use App\Domain\TwoFactor\ValueObjects\RecoveryCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecoveryCodeTest extends TestCase
{
    public function test_generated_codes_have_two_groups_of_five_unambiguous_characters(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = RecoveryCode::generate()->value;

            $this->assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]{5}-[23456789abcdefghjkmnpqrstuvwxyz]{5}$/', $code);
        }
    }

    public function test_the_alphabet_leaves_out_characters_that_look_alike(): void
    {
        foreach (['0', '1', 'o', 'l', 'i'] as $character) {
            $this->assertStringNotContainsString($character, RecoveryCode::ALPHABET);
        }
    }

    public function test_generated_codes_differ(): void
    {
        $codes = array_map(static fn (): string => RecoveryCode::generate()->value, range(1, 50));

        $this->assertCount(50, array_unique($codes));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedCodes(): iterable
    {
        yield 'as shown' => ['abcde-fghjk', 'abcde-fghjk'];
        yield 'in capitals' => ['ABCDE-FGHJK', 'abcde-fghjk'];
        yield 'without the hyphen' => ['abcdefghjk', 'abcde-fghjk'];
        yield 'with spaces' => [' abcde fghjk ', 'abcde-fghjk'];
        yield 'digits' => ['23456-789ab', '23456-789ab'];
    }

    #[DataProvider('acceptedCodes')]
    public function test_it_reads_what_people_type(string $input, string $expected): void
    {
        $this->assertSame($expected, RecoveryCode::fromString($input)->value);
        $this->assertSame($expected, RecoveryCode::tryFrom($input)?->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['abcde-fghj'];
        yield 'too long' => ['abcde-fghjkm'];
        yield 'a confusable character' => ['abcde-fghj1'];
        yield 'the letter o' => ['abcdo-fghjk'];
        yield 'misplaced hyphen' => ['abcd-efghjk'];
        yield 'two hyphens' => ['abcde--fghjk'];
        yield 'a totp code' => ['123456'];
        yield 'accented letter' => ['ábcde-fghjk'];
    }

    #[DataProvider('rejectedCodes')]
    public function test_it_rejects_anything_else(string $input): void
    {
        $this->assertNull(RecoveryCode::tryFrom($input));

        $this->expectException(InvalidTwoFactorCodeException::class);
        RecoveryCode::fromString($input);
    }

    public function test_the_hash_depends_only_on_the_normalised_code(): void
    {
        $this->assertSame(RecoveryCode::fromString('abcde-fghjk')->hash(), RecoveryCode::fromString('ABCDEFGHJK')->hash());
        $this->assertNotSame(RecoveryCode::fromString('abcde-fghjk')->hash(), RecoveryCode::fromString('abcde-fghjm')->hash());
        $this->assertStringNotContainsString('abcde', RecoveryCode::fromString('abcde-fghjk')->hash());
    }
}
