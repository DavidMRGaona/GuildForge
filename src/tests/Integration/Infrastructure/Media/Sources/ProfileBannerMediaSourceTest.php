<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media\Sources;

use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Jobs\RefreshMediaUsagesJob;
use App\Infrastructure\Media\Sources\ProfileBannerMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The banners of public profiles (core#743) count as used by their profile, so the media library never
 * takes one for unused while a profile shows it.
 */
final class ProfileBannerMediaSourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_claims_the_banners_folder_and_is_registered(): void
    {
        $source = $this->source();

        $this->assertSame('core.profile-banner', $source->type());
        $this->assertSame(['profiles/banners/'], $source->folders());
        $this->assertSame([], $source->excludedSubfolders());
        $this->assertFalse($source->hidden());
        $this->assertSame('Banners de perfil', $source->label());
        $this->assertContains('core.profile-banner', array_map(
            static fn ($registered): string => $registered->type(),
            app(MediaSourceRegistryInterface::class)->sources(),
        ));
    }

    public function test_each_profile_with_a_banner_uses_it_under_its_public_name(): void
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => 'Ana Prueba']);
        $this->setBanner($owner, 'profiles/banners/2026/11/a.jpg');
        $nameless = UserModel::factory()->create(['name' => 'Otra cuenta', 'display_name' => null]);
        $this->setBanner($nameless, 'profiles/banners/2026/11/b.jpg');
        UserModel::factory()->create(['display_name' => 'Sin banner']);

        $usages = iterator_to_array($this->source()->usages(), false);
        usort($usages, static fn ($a, $b): int => strcmp($a->storedPath, $b->storedPath));

        $this->assertCount(2, $usages);
        $this->assertSame((string) $owner->id, $usages[0]->recordId);
        $this->assertSame('Ana Prueba', $usages[0]->title);
        $this->assertSame('Banner', $usages[0]->role);
        // Never the account name: the address stands in for a missing public name
        $handle = UserProfileModel::query()->findOrFail($nameless->id)->handle;
        $this->assertSame('/miembros/'.$handle, $usages[1]->title);

        $this->assertTrue($this->source()->usesFile(new MediaFileId('profiles/banners/2026/11/a')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('profiles/banners/2026/11/c')));
        $this->assertCount(1, $this->source()->usagesFor((string) $owner->id));
        $this->assertSame([], $this->source()->usagesFor('not-a-uuid'));
        $this->assertNull($this->source()->editUrl((string) $owner->id, $owner));
    }

    public function test_a_deactivated_account_still_uses_its_banner(): void
    {
        $owner = UserModel::factory()->create(['display_name' => 'Ana Prueba']);
        $this->setBanner($owner, 'profiles/banners/2026/11/a.jpg');
        $owner->delete();

        $this->assertTrue($this->source()->usesFile(new MediaFileId('profiles/banners/2026/11/a')));
    }

    public function test_changing_a_banner_refreshes_its_usages_and_nothing_else_does(): void
    {
        $owner = UserModel::factory()->create(['display_name' => 'Ana Prueba']);
        Bus::fake();

        UserProfileModel::query()->findOrFail($owner->id)->update(['bio' => 'Hola']);
        Bus::assertNotDispatched(RefreshMediaUsagesJob::class);

        $this->setBanner($owner, 'profiles/banners/2026/11/a.jpg');
        Bus::assertDispatched(RefreshMediaUsagesJob::class, static fn (RefreshMediaUsagesJob $job): bool => $job->type === 'core.profile-banner' && $job->recordId === (string) $owner->id);
    }

    public function test_a_new_public_name_or_address_refreshes_the_title_of_the_usage(): void
    {
        $owner = UserModel::factory()->create(['display_name' => 'Ana Prueba']);
        $this->setBanner($owner, 'profiles/banners/2026/11/a.jpg');
        Bus::fake();

        $owner->update(['display_name' => 'Ana Nueva']);
        Bus::assertDispatched(RefreshMediaUsagesJob::class, static fn (RefreshMediaUsagesJob $job): bool => $job->type === 'core.profile-banner' && $job->recordId === (string) $owner->id);

        Bus::fake();
        UserProfileModel::query()->findOrFail($owner->id)->update(['handle' => 'ana-rol']);
        Bus::assertDispatched(RefreshMediaUsagesJob::class, static fn (RefreshMediaUsagesJob $job): bool => $job->type === 'core.profile-banner');

        // Saved on every sign-in: nothing to refresh when neither changed
        Bus::fake();
        $owner->forceFill(['remember_token' => 'otro-token'])->save();
        Bus::assertNotDispatched(RefreshMediaUsagesJob::class, static fn (RefreshMediaUsagesJob $job): bool => $job->type === 'core.profile-banner');
    }

    public function test_checking_a_file_reads_only_the_banners(): void
    {
        $owner = UserModel::factory()->create(['display_name' => 'Ana Prueba']);
        $this->setBanner($owner, 'profiles/banners/2026/11/a.jpg');
        DB::enableQueryLog();

        $this->assertTrue($this->source()->usesFile(new MediaFileId('profiles/banners/2026/11/a')));

        $tables = array_map(static fn (array $query): string => $query['query'], DB::getQueryLog());
        $this->assertNotEmpty($tables);
        foreach ($tables as $sql) {
            $this->assertStringNotContainsString('"users"', $sql);
        }
    }

    private function setBanner(UserModel $owner, string $banner): void
    {
        UserProfileModel::query()->findOrFail($owner->id)->update(['banner_public_id' => $banner]);
    }

    private function source(): ProfileBannerMediaSource
    {
        return app(ProfileBannerMediaSource::class);
    }
}
