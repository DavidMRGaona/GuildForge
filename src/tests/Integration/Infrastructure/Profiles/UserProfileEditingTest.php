<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Profiles\DTOs\ProfileUpdateDTO;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Domain\Profiles\Exceptions\InvalidProfileHandleException;
use App\Domain\Profiles\Exceptions\ProfileHandleChangeTooSoonException;
use App\Domain\Profiles\Exceptions\ProfileHandleTakenException;
use App\Domain\Profiles\ProfileHandleAvailability;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Domain\Profiles\ValueObjects\ProfileLayout;
use App\Domain\Profiles\ValueObjects\ProfileLayoutEntry;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * What the owner saves from the editor of their profile (core#743): an address chosen goes through the
 * same history as every other, so nobody ever gets somebody else's, and only once every 30 days.
 */
final class UserProfileEditingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var MediaFileReleaserInterface&MockInterface */
    private MediaFileReleaserInterface $releaser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->releaser = Mockery::spy(MediaFileReleaserInterface::class);
        $this->app->instance(MediaFileReleaserInterface::class, $this->releaser);
        Carbon::setTestNow('2026-11-15 10:00:00');
    }

    public function test_it_saves_the_choices_and_keeps_an_unchanged_address_as_it_was(): void
    {
        $owner = $this->owner();

        $this->service()->update($owner->id, $this->update(
            handle: 'ana-prueba',
            visibility: ProfileVisibility::Public,
            bio: "Juego a rol.\nY a wargames.",
            showMembership: true,
            sections: [new ProfileLayoutEntry('core.about', false)],
        ));

        $profile = $this->profileOf($owner);
        $this->assertSame('ana-prueba', $profile->handle);
        $this->assertFalse($profile->handle_chosen);
        $this->assertNull($profile->handle_changed_at);
        $this->assertSame(ProfileVisibility::Public, $profile->visibility);
        $this->assertSame("Juego a rol.\nY a wargames.", $profile->bio);
        $this->assertTrue($profile->show_membership);
        $this->assertSame(['sections' => [['key' => 'core.about', 'enabled' => false]], 'stats' => []], $profile->layout->toArray());
    }

    public function test_a_new_address_goes_through_the_history_and_is_marked_as_chosen(): void
    {
        $owner = $this->owner();

        $this->service()->update($owner->id, $this->update(handle: '  Ana-Rol '));

        $profile = $this->profileOf($owner);
        $this->assertSame('ana-rol', $profile->handle);
        $this->assertTrue($profile->handle_chosen);
        $this->assertTrue($profile->handle_changed_at?->equalTo(now()));

        $new = UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('ana-rol'));
        $this->assertSame($owner->id, $new->user_id);
        $this->assertNull($new->retired_at);
        $this->assertNotNull(UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('ana-prueba'))->retired_at);
    }

    public function test_once_chosen_the_public_name_no_longer_moves_the_address(): void
    {
        $owner = $this->owner();
        $this->service()->update($owner->id, $this->update(handle: 'ana-rol'));

        $owner->update(['display_name' => 'Ana Otra']);

        $this->assertSame('ana-rol', $this->profileOf($owner)->handle);
    }

    public function test_moving_with_the_public_name_does_not_count_as_a_choice(): void
    {
        $owner = $this->owner();
        $owner->update(['display_name' => 'Ana Otra']);

        $profile = $this->profileOf($owner);
        $this->assertSame('ana-otra', $profile->handle);
        $this->assertNull($profile->handle_changed_at);
        $this->assertNull($this->service()->nextHandleChangeAt($owner->id));
    }

    public function test_the_address_of_somebody_else_is_never_given_and_nothing_is_saved(): void
    {
        $owner = $this->owner();
        UserModel::factory()->create(['display_name' => 'Berta Prueba']);

        try {
            $this->service()->update($owner->id, $this->update(handle: 'berta-prueba', bio: 'Nuevo texto'));
            $this->fail('An address held by somebody else was given.');
        } catch (ProfileHandleTakenException) {
            // Expected
        }

        $profile = $this->profileOf($owner);
        $this->assertSame('ana-prueba', $profile->handle);
        $this->assertNull($profile->bio);
    }

    public function test_an_address_somebody_else_held_before_is_never_given(): void
    {
        $owner = $this->owner();
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $other->update(['display_name' => 'Berta Nueva']);

        $this->expectException(ProfileHandleTakenException::class);

        $this->service()->update($owner->id, $this->update(handle: 'berta-prueba'));
    }

    public function test_an_address_of_a_forgotten_account_is_never_given(): void
    {
        $owner = $this->owner();
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $this->service()->forget($other->id);
        $this->assertNull(UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('berta-prueba'))->user_id);

        $this->expectException(ProfileHandleTakenException::class);

        $this->service()->update($owner->id, $this->update(handle: 'berta-prueba'));
    }

    public function test_the_owner_may_take_back_an_address_of_their_own(): void
    {
        $owner = $this->owner();
        $owner->update(['display_name' => 'Ana Otra']);

        $this->service()->update($owner->id, $this->update(handle: 'ana-prueba'));

        $this->assertSame('ana-prueba', $this->profileOf($owner)->handle);
        $this->assertNull(UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('ana-prueba'))->retired_at);
        $this->assertNotNull(UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('ana-otra'))->retired_at);
    }

    public function test_a_reserved_word_is_never_an_address(): void
    {
        $owner = $this->owner();

        $this->expectException(InvalidProfileHandleException::class);

        $this->service()->update($owner->id, $this->update(handle: 'admin'));
    }

    public function test_a_current_address_that_became_a_reserved_word_does_not_block_the_rest(): void
    {
        $owner = $this->owner();
        // Given before the word was reserved
        UserProfileModel::query()->whereKey($owner->id)->update(['handle' => 'staff']);

        $this->service()->update($owner->id, $this->update(handle: ' Staff ', bio: 'Se guarda'));

        $profile = $this->profileOf($owner);
        $this->assertSame('staff', $profile->handle);
        $this->assertSame('Se guarda', $profile->bio);
        $this->assertFalse($profile->handle_chosen);
        $this->assertSame(ProfileHandleAvailability::Current, $this->service()->handleAvailability($owner->id, 'staff'));
    }

    public function test_the_address_can_be_chosen_again_only_after_thirty_days(): void
    {
        $owner = $this->owner();
        $this->service()->update($owner->id, $this->update(handle: 'ana-rol'));

        Carbon::setTestNow('2026-12-15 09:59:59');
        try {
            $this->service()->update($owner->id, $this->update(handle: 'ana-dados', bio: 'No se guarda'));
            $this->fail('The address was chosen again within 30 days.');
        } catch (ProfileHandleChangeTooSoonException $exception) {
            $this->assertEquals(Carbon::parse('2026-12-15 10:00:00'), Carbon::instance($exception->availableAt));
        }
        $this->assertSame('ana-rol', $this->profileOf($owner)->handle);
        $this->assertNull($this->profileOf($owner)->bio);

        // The rest saves meanwhile, with the address left as it is
        $this->service()->update($owner->id, $this->update(handle: 'ana-rol', bio: 'Se guarda'));
        $this->assertSame('Se guarda', $this->profileOf($owner)->bio);

        Carbon::setTestNow('2026-12-15 10:00:00');
        $this->assertNull($this->service()->nextHandleChangeAt($owner->id));
        $this->service()->update($owner->id, $this->update(handle: 'ana-dados'));
        $this->assertSame('ana-dados', $this->profileOf($owner)->handle);
    }

    public function test_it_tells_when_the_address_can_be_chosen_again(): void
    {
        $owner = $this->owner();
        $this->service()->update($owner->id, $this->update(handle: 'ana-rol'));

        $this->assertEquals(Carbon::parse('2026-12-15 10:00:00'), Carbon::instance($this->service()->nextHandleChangeAt($owner->id) ?? $this->fail('No date.')));
        $this->assertNull($this->service()->nextHandleChangeAt('00000000-0000-0000-0000-000000000000'));
    }

    public function test_the_layout_keeps_what_it_said_of_sections_not_offered_now(): void
    {
        $owner = $this->owner();
        UserProfileModel::query()->findOrFail($owner->id)->update(['layout' => ProfileLayout::fromArray([
            'sections' => [['key' => 'forum.latest-posts', 'enabled' => false]],
            'stats' => [],
        ])]);

        $this->service()->update($owner->id, $this->update(
            sections: [new ProfileLayoutEntry('core.about', true)],
            stats: [new ProfileLayoutEntry('core.forged', true)],
        ));

        $this->assertSame([
            'sections' => [['key' => 'core.about', 'enabled' => true], ['key' => 'forum.latest-posts', 'enabled' => false]],
            'stats' => [],
        ], $this->profileOf($owner)->layout->toArray());
    }

    public function test_it_tells_whether_an_address_can_be_chosen_without_saying_whose_it_is(): void
    {
        $owner = $this->owner();
        $owner->update(['display_name' => 'Ana Otra']);
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $other->update(['display_name' => 'Berta Nueva']);
        $service = $this->service();

        $this->assertSame(ProfileHandleAvailability::Current, $service->handleAvailability($owner->id, 'ana-otra'));
        $this->assertSame(ProfileHandleAvailability::Current, $service->handleAvailability($owner->id, ' Ana-Otra '));
        $this->assertSame(ProfileHandleAvailability::Available, $service->handleAvailability($owner->id, 'ana-dados'));
        $this->assertSame(ProfileHandleAvailability::Available, $service->handleAvailability($owner->id, 'ana-prueba'));
        $this->assertSame(ProfileHandleAvailability::Unavailable, $service->handleAvailability($owner->id, 'berta-nueva'));
        $this->assertSame(ProfileHandleAvailability::Unavailable, $service->handleAvailability($owner->id, 'berta-prueba'));
        $this->assertSame(ProfileHandleAvailability::Unavailable, $service->handleAvailability($owner->id, 'admin'));
        $this->assertSame(ProfileHandleAvailability::Malformed, $service->handleAvailability($owner->id, 'a'));
        $this->assertSame(ProfileHandleAvailability::Malformed, $service->handleAvailability($owner->id, 'ana--prueba'));
    }

    public function test_a_new_banner_is_stored_in_its_folder_and_the_previous_one_released(): void
    {
        Storage::fake('images');
        $owner = $this->owner();
        UserProfileModel::query()->findOrFail($owner->id)->update(['banner_public_id' => 'profiles/banners/2026/10/old.jpg']);

        $stored = $this->service()->replaceBanner($owner->id, UploadedFile::fake()->image('banner.png', 1500, 500));

        $this->assertMatchesRegularExpression('#^profiles/banners/2026/11/[0-9a-f-]{36}\.png$#', $stored);
        Storage::disk('images')->assertExists($stored);
        $this->assertSame($stored, $this->profileOf($owner)->banner_public_id);
        $this->releaser->shouldHaveReceived('release')->once()->with('profiles/banners/2026/10/old.jpg');
    }

    public function test_removing_the_banner_releases_it(): void
    {
        $owner = $this->owner();
        UserProfileModel::query()->findOrFail($owner->id)->update(['banner_public_id' => 'profiles/banners/2026/10/old.jpg']);

        $this->service()->removeBanner($owner->id);
        $this->service()->removeBanner($owner->id);

        $this->assertNull($this->profileOf($owner)->banner_public_id);
        $this->releaser->shouldHaveReceived('release')->once()->with('profiles/banners/2026/10/old.jpg');
    }

    private function owner(): UserModel
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => 'Ana Prueba']);
        $this->assertSame('ana-prueba', $this->profileOf($owner)->handle);

        return $owner;
    }

    /**
     * @param  list<ProfileLayoutEntry>  $sections
     * @param  list<ProfileLayoutEntry>  $stats
     */
    private function update(
        string $handle = 'ana-prueba',
        ProfileVisibility $visibility = ProfileVisibility::Members,
        ?string $bio = null,
        bool $showMembership = false,
        array $sections = [],
        array $stats = [],
    ): ProfileUpdateDTO {
        return new ProfileUpdateDTO($handle, $visibility, $bio, $showMembership, $sections, $stats, ['core.about'], []);
    }

    private function service(): UserProfileServiceInterface
    {
        return app(UserProfileServiceInterface::class);
    }

    private function profileOf(UserModel $user): UserProfileModel
    {
        return UserProfileModel::query()->findOrFail($user->id);
    }
}
