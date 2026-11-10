<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media\Sources;

use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Sources\AvatarMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class AvatarMediaSourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_is_hidden_and_claims_both_folders(): void
    {
        $source = app(AvatarMediaSource::class);

        $this->assertTrue($source->hidden());
        $this->assertSame(['avatars/', 'users/'], $source->folders());
        $this->assertSame([], $source->excludedSubfolders());
    }

    public function test_lists_users_with_an_avatar_and_never_links_to_them(): void
    {
        $user = UserModel::factory()->create(['avatar_public_id' => 'avatars/a.jpg']);
        UserModel::factory()->create(['avatar_public_id' => null]);
        $source = app(AvatarMediaSource::class);

        $usages = iterator_to_array($source->usages(), false);

        $this->assertCount(1, $usages);
        $this->assertSame((string) $user->getKey(), $usages[0]->recordId);
        $this->assertTrue($source->usesFile(new MediaFileId('avatars/a')));
        $this->assertFalse($source->usesFile(new MediaFileId('avatars/b')));
        $this->assertNull($source->editUrl((string) $user->getKey(), $user));
    }

    public function test_soft_deleted_users_count(): void
    {
        $user = UserModel::factory()->create(['avatar_public_id' => 'users/x.jpg']);
        $user->delete();

        $this->assertTrue(app(AvatarMediaSource::class)->usesFile(new MediaFileId('users/x')));
    }
}
