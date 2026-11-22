<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Profiles;

use App\Domain\Profiles\ProfileVisibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileVisibilityTest extends TestCase
{
    #[DataProvider('storedValues')]
    public function test_each_audience_reads_back_from_what_is_stored(string $stored): void
    {
        $this->assertSame($stored, ProfileVisibility::fromStored($stored)->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function storedValues(): array
    {
        return ['public' => ['public'], 'members' => ['members'], 'association' => ['association'], 'hidden' => ['hidden']];
    }

    public function test_signed_in_accounts_are_the_default(): void
    {
        $this->assertSame(ProfileVisibility::Members, ProfileVisibility::default());
    }

    public function test_an_unknown_stored_value_is_hidden(): void
    {
        $this->assertSame(ProfileVisibility::Hidden, ProfileVisibility::fromStored('everyone'));
        $this->assertSame(ProfileVisibility::Hidden, ProfileVisibility::fromStored(null));
        $this->assertSame(ProfileVisibility::Public, ProfileVisibility::fromStored('public'));
    }

    public function test_only_public_and_members_profiles_are_seen_by_every_signed_in_member(): void
    {
        $this->assertTrue(ProfileVisibility::Public->isSeenByEveryMember());
        $this->assertTrue(ProfileVisibility::Members->isSeenByEveryMember());
        $this->assertFalse(ProfileVisibility::Association->isSeenByEveryMember());
        $this->assertFalse(ProfileVisibility::Hidden->isSeenByEveryMember());
    }
}
