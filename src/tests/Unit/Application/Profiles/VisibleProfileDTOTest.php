<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Profiles;

use App\Application\Profiles\DTOs\VisibleProfileDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * VisibleProfileDTO (core#840): a public profile a person may see, checked when it is built, so a
 * module never receives an account id in another case, an address the page would not answer or a
 * blank public name.
 */
final class VisibleProfileDTOTest extends TestCase
{
    private const string ANA = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e5f';

    public function test_it_keeps_what_it_was_given(): void
    {
        $profile = new VisibleProfileDTO(self::ANA, 'ana-garcia', 'Ana García', 'avatars/ana.jpg');

        $this->assertSame(self::ANA, $profile->userId);
        $this->assertSame('ana-garcia', $profile->handle);
        $this->assertSame('Ana García', $profile->publicName);
        $this->assertSame('avatars/ana.jpg', $profile->avatarPublicId);
    }

    public function test_the_public_name_and_the_avatar_may_be_missing(): void
    {
        $profile = new VisibleProfileDTO(self::ANA, 'miembro-a2b3c4', null, null);

        $this->assertNull($profile->publicName);
        $this->assertNull($profile->avatarPublicId);
    }

    public function test_an_account_id_in_upper_case_is_kept_in_lower_case(): void
    {
        $this->assertSame(self::ANA, (new VisibleProfileDTO(strtoupper(self::ANA), 'ana', 'Ana', null))->userId);
    }

    public function test_it_carries_only_the_account_id_the_address_the_public_name_and_the_avatar(): void
    {
        $this->assertSame(
            ['userId', 'handle', 'publicName', 'avatarPublicId'],
            array_keys(get_object_vars(new VisibleProfileDTO(self::ANA, 'ana', 'Ana', null))),
        );
    }

    /**
     * @return iterable<string, array{string, string, string|null, string|null}>
     */
    public static function refused(): iterable
    {
        yield 'an account id that is not a UUID' => ['42', 'ana', 'Ana', null];
        yield 'an empty account id' => ['', 'ana', 'Ana', null];
        yield 'an address too short' => [self::ANA, 'an', 'Ana', null];
        yield 'an address too long' => [self::ANA, str_repeat('a', 31), 'Ana', null];
        yield 'an address in capitals' => [self::ANA, 'Ana', 'Ana', null];
        yield 'an address with a slash' => [self::ANA, 'ana/../admin', 'Ana', null];
        yield 'an address ending in a hyphen' => [self::ANA, 'ana-', 'Ana', null];
        yield 'a blank public name' => [self::ANA, 'ana', "  \t ", null];
        yield 'a public name with blanks around it' => [self::ANA, 'ana', ' Ana ', null];
        yield 'a blank avatar' => [self::ANA, 'ana', 'Ana', '  '];
    }

    #[DataProvider('refused')]
    public function test_what_a_profile_page_would_not_show_is_refused(string $userId, string $handle, ?string $publicName, ?string $avatarPublicId): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VisibleProfileDTO($userId, $handle, $publicName, $avatarPublicId);
    }
}
