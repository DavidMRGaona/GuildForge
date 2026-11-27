<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Profiles;

use App\Domain\Profiles\PublicName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function values(): iterable
    {
        yield 'null' => [null, true];
        yield 'empty' => ['', true];
        yield 'spaces' => ['   ', true];
        yield 'no-break spaces and tabs' => ["\u{00A0}\t\u{2003}", true];
        yield 'a name' => ['Ana', false];
        yield 'a name with spaces around' => ['  Ana  ', false];
    }

    #[DataProvider('values')]
    public function test_a_public_name_is_missing_when_it_holds_nothing_but_blanks(?string $value, bool $missing): void
    {
        $this->assertSame($missing, PublicName::isMissing($value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function accountNames(): iterable
    {
        yield 'one word' => ['Ana', 'Ana'];
        yield 'name and surnames' => ['Ana Example Sample', 'Ana'];
        yield 'blanks around' => ['   Ana   Example ', 'Ana'];
        yield 'tabs and no-break spaces' => ["Ana\u{00A0}Example\tSample", 'Ana'];
        yield 'accents kept' => ['Íñigo Ejemplo', 'Íñigo'];
        yield 'nothing but blanks' => ['   ', ''];
        yield 'empty' => ['', ''];
    }

    #[DataProvider('accountNames')]
    public function test_the_suggestion_is_only_the_first_word_of_the_account_name(string $accountName, string $suggestion): void
    {
        $this->assertSame($suggestion, PublicName::suggestFrom($accountName));
    }

    public function test_a_suggestion_never_goes_past_the_maximum_length(): void
    {
        $suggestion = PublicName::suggestFrom(str_repeat('á', PublicName::MAX_LENGTH + 20).' Example');

        $this->assertSame(PublicName::MAX_LENGTH, mb_strlen($suggestion));
    }
}
