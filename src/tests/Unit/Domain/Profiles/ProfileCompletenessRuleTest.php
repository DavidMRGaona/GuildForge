<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Profiles;

use App\Domain\Profiles\ProfileCompletenessRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileCompletenessRuleTest extends TestCase
{
    public function test_a_profile_with_public_name_avatar_and_about_me_is_complete(): void
    {
        $this->assertTrue(ProfileCompletenessRule::isComplete('Ana', 'avatars/ana.png', 'Juego de rol desde 1999.'));
    }

    /**
     * @return array<string, array{?string, ?string, ?string}>
     */
    public static function incompleteProfiles(): array
    {
        return [
            'no public name' => [null, 'avatars/a.png', 'Hola'],
            'blank public name' => ['   ', 'avatars/a.png', 'Hola'],
            'no avatar' => ['Ana', null, 'Hola'],
            'empty avatar' => ['Ana', '', 'Hola'],
            'no about me' => ['Ana', 'avatars/a.png', null],
            'blank about me' => ['Ana', 'avatars/a.png', " \n\t "],
            'nothing' => [null, null, null],
        ];
    }

    #[DataProvider('incompleteProfiles')]
    public function test_a_profile_missing_any_part_is_not_complete(?string $name, ?string $avatar, ?string $bio): void
    {
        $this->assertFalse(ProfileCompletenessRule::isComplete($name, $avatar, $bio));
    }
}
