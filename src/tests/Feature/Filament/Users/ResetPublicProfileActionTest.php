<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Profiles\ProfileVisibility;
use App\Filament\Actions\ResetPublicProfileAction;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Impersonation\ImpersonationContext;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * «Restablecer perfil público» (core#746): whoever moderates takes away a bio or a banner that should not
 * be there, from the account's page in the panel. The address, the visibility and the layout stay as the
 * owner left them. Only with `profiles.moderate`, checked again when the action runs.
 */
final class ResetPublicProfileActionTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    /** @var MediaFileReleaserInterface&MockInterface */
    private MediaFileReleaserInterface $releaser;

    private UserModel $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->releaser = Mockery::spy(MediaFileReleaserInterface::class);
        $this->app->instance(MediaFileReleaserInterface::class, $this->releaser);

        $this->owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => 'Ana Prueba']);
        UserProfileModel::query()->findOrFail($this->owner->id)->update([
            'bio' => 'Un texto que no debería estar aquí.',
            'banner_public_id' => 'profiles/banners/2026/10/banner.jpg',
            'visibility' => ProfileVisibility::Public,
        ]);
    }

    public function test_it_is_offered_to_whoever_may_moderate_profiles(): void
    {
        $this->actingAs($this->moderator());

        $this->editPage()->assertActionVisible(ResetPublicProfileAction::NAME);
    }

    public function test_an_administrator_may_reset_it(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->editPage()->assertActionVisible(ResetPublicProfileAction::NAME);
    }

    public function test_it_is_not_offered_without_the_permission(): void
    {
        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.view', 'users.update']));

        $this->editPage()->assertActionHidden(ResetPublicProfileAction::NAME);
    }

    public function test_a_forged_call_without_the_permission_changes_nothing(): void
    {
        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.view', 'users.update']));
        $log = Log::spy();

        // Straight to the Livewire methods, as a crafted request would: the hidden button is not needed
        $this->editPage()
            ->call('mountAction', ResetPublicProfileAction::NAME)
            ->set('mountedActions.0.data.confirmation', true)
            ->call('callMountedAction');

        $this->assertUntouched();
        $log->shouldNotHaveReceived('info', ['Public profile reset from the panel', Mockery::any()]);
    }

    public function test_it_is_not_offered_for_an_anonymized_account(): void
    {
        $this->actingAs($this->moderator());
        $this->owner->forceFill(['anonymized_at' => now(), 'deleted_at' => now()])->save();

        $this->editPage()->assertActionHidden(ResetPublicProfileAction::NAME);
    }

    public function test_it_needs_the_box_ticked(): void
    {
        $this->actingAs($this->moderator());

        $this->editPage()
            ->callAction(ResetPublicProfileAction::NAME, ['confirmation' => false])
            ->assertHasActionErrors(['confirmation']);

        $this->assertUntouched();
    }

    public function test_it_takes_away_the_bio_and_the_banner_and_keeps_the_address(): void
    {
        $moderator = $this->moderator();
        $this->actingAs($moderator);
        $handle = UserProfileModel::query()->findOrFail($this->owner->id)->handle;
        $log = Log::spy();

        $this->editPage()
            ->callAction(ResetPublicProfileAction::NAME, ['confirmation' => true])
            ->assertHasNoActionErrors()
            ->assertNotified(__('filament.users.actions.resetPublicProfile.done'));

        $profile = UserProfileModel::query()->findOrFail($this->owner->id);
        $this->assertNull($profile->bio);
        $this->assertNull($profile->banner_public_id);
        $this->assertSame($handle, $profile->handle);
        $this->assertSame(ProfileVisibility::Public, $profile->visibility);
        $this->releaser->shouldHaveReceived('release')->once()->with('profiles/banners/2026/10/banner.jpg');
        $log->shouldHaveReceived('info')
            ->with('Public profile reset from the panel', [
                'actor_id' => (string) $moderator->id,
                'user_id' => (string) $this->owner->id,
            ])
            ->once();
    }

    public function test_it_says_so_when_there_was_nothing_to_take_away(): void
    {
        $this->actingAs($this->moderator());
        UserProfileModel::query()->findOrFail($this->owner->id)->update(['bio' => null, 'banner_public_id' => null]);
        $log = Log::spy();

        $this->editPage()
            ->callAction(ResetPublicProfileAction::NAME, ['confirmation' => true])
            ->assertNotified(__('filament.users.actions.resetPublicProfile.nothing'));

        $this->releaser->shouldNotHaveReceived('release');
        $log->shouldNotHaveReceived('info', ['Public profile reset from the panel', Mockery::any()]);
    }

    public function test_the_policy_lets_a_moderator_reset_an_account_they_could_edit(): void
    {
        $this->assertTrue(Gate::forUser($this->moderator())->allows('resetPublicProfile', $this->owner));
    }

    public function test_the_policy_never_lets_a_moderator_reset_an_administrator(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertFalse(Gate::forUser($this->moderator())->allows('resetPublicProfile', $admin));
    }

    public function test_the_policy_never_lets_a_moderator_reset_an_account_holding_more_than_they_do(): void
    {
        $stronger = $this->editorWithPermissions(['users.view_any', 'users.view', 'users.update', 'profiles.moderate', 'roles.delete']);

        $this->assertFalse(Gate::forUser($this->moderator())->allows('resetPublicProfile', $stronger));
    }

    public function test_it_is_refused_while_impersonating_in_read_only_mode(): void
    {
        $moderator = $this->moderator();
        $moderator->forceFill(['name' => 'Marta Ejemplo', 'display_name' => 'Marta'])->save();
        $this->actingAs($moderator);
        app(ImpersonationContext::class)->activate(UserModel::factory()->admin()->create(), $moderator, ImpersonationMode::ReadOnly, 'log-id');

        $this->editPage()
            ->callAction(ResetPublicProfileAction::NAME, ['confirmation' => true])
            ->assertNotified(__('filament.impersonation_read_only.refused', ['name' => 'Marta']));

        $this->assertUntouched();
    }

    private function moderator(): UserModel
    {
        return $this->editorWithPermissions(['users.view_any', 'users.view', 'users.update', 'profiles.moderate']);
    }

    /**
     * @return Testable<EditUser>
     */
    private function editPage(): Testable
    {
        return Livewire::test(EditUser::class, ['record' => $this->owner->getRouteKey()]);
    }

    private function assertUntouched(): void
    {
        $profile = UserProfileModel::query()->findOrFail($this->owner->id);
        $this->assertSame('Un texto que no debería estar aquí.', $profile->bio);
        $this->assertSame('profiles/banners/2026/10/banner.jpg', $profile->banner_public_id);
        $this->releaser->shouldNotHaveReceived('release');
    }
}
