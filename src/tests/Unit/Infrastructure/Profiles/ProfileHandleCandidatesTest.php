<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Profiles;

use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Profiles\ProfileHandleCandidates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileHandleCandidatesTest extends TestCase
{
    public function test_every_random_suffix_has_a_digit(): void
    {
        for ($run = 0; $run < 50; $run++) {
            foreach (ProfileHandleCandidates::for(null) as $handle) {
                $this->assertMatchesRegularExpression('/^miembro-[a-z2-9]*[2-9][a-z2-9]*$/', $handle->value);
                $this->assertTrue(ProfileHandleCandidates::isRandom($handle->value));
            }
        }
    }

    public function test_a_random_suffix_after_a_slug_has_a_digit_too(): void
    {
        $candidates = array_map(static fn (ProfileHandle $handle): string => $handle->value, iterator_to_array(ProfileHandleCandidates::for('Ana'), false));
        $random = array_slice($candidates, 20);

        $this->assertNotSame([], $random);
        foreach ($random as $handle) {
            $this->assertTrue(ProfileHandleCandidates::follows($handle, 'Ana'));
            $this->assertMatchesRegularExpression('/^ana-[a-z2-9]*[2-9][a-z2-9]*$/', $handle);
        }
    }

    #[DataProvider('wordsThatLookRandom')]
    public function test_a_word_is_never_taken_for_a_random_suffix(string $handle, ?string $publicName): void
    {
        $this->assertFalse(ProfileHandleCandidates::follows($handle, $publicName));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function wordsThatLookRandom(): array
    {
        return [
            'surname after the slug' => ['ana-suarez', 'Ana'],
            'another surname' => ['ana-vargas', 'Ana'],
            'surname after the random prefix' => ['miembro-suarez', null],
        ];
    }

    #[DataProvider('handlesThatFollow')]
    public function test_what_the_public_name_gives_follows_it(string $handle, ?string $publicName): void
    {
        $this->assertTrue(ProfileHandleCandidates::follows($handle, $publicName));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function handlesThatFollow(): array
    {
        return [
            'the slug' => ['ana-suarez', 'Ana Suárez'],
            'numbered' => ['ana-7', 'Ana.'],
            'random suffix' => ['ana-k3pqrs', 'ANA'],
            'random without a name' => ['miembro-2bcdef', ''],
        ];
    }
}
