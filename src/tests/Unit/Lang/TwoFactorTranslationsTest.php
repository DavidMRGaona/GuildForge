<?php

declare(strict_types=1);

namespace Tests\Unit\Lang;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * The two-factor texts exist in both languages (core#769 added the account-settings and app-change
 * ones): a key missing from one shows its raw name to the people who use it.
 */
final class TwoFactorTranslationsTest extends TestCase
{
    public function test_every_spanish_key_has_an_english_text_and_the_other_way_round(): void
    {
        $spanish = array_keys(Arr::dot($this->load('es')));
        $english = array_keys(Arr::dot($this->load('en')));

        $this->assertSame([], array_values(array_diff($spanish, $english)), 'Missing in English');
        $this->assertSame([], array_values(array_diff($english, $spanish)), 'Missing in Spanish');
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $locale): array
    {
        return require dirname(__DIR__, 3).'/lang/'.$locale.'/two_factor.php';
    }
}
