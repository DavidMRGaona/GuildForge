<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Filament\Actions\ResetTwoFactorAction;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Impersonation\ImpersonationContext;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Notifications\TwoFactor\TwoFactorResetNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * «Restablecer el doble factor» (core#771): the administration takes the two-factor authentication
 * of an account that lost its phone and its recovery codes. Only the administrator role holds the
 * permission, the account is never one's own or an anonymized one, and the policy is checked again
 * when the action runs.
 */
final class ResetTwoFactorActionTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    private UserModel $admin;

    private UserModel $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        app()->setLocale('es');
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));

        $this->admin = UserModel::factory()->admin()->create();
        $this->owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => 'Ana Prueba']);
        $this->enable($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_administrator_is_offered_it_for_an_account_with_two_factor(): void
    {
        $this->actingAs($this->admin);

        $this->editPage()->assertActionVisible(ResetTwoFactorAction::NAME);
    }

    public function test_it_is_not_offered_for_an_account_without_two_factor(): void
    {
        $this->actingAs($this->admin);
        $other = UserModel::factory()->create();

        $this->editPage($other)->assertActionHidden(ResetTwoFactorAction::NAME);

        // A setup that was never confirmed is not two-factor authentication either
        $pending = UserModel::factory()->create();
        $this->app->make(TwoFactorServiceInterface::class)->beginSetup((string) $pending->id);
        $this->editPage($pending)->assertActionHidden(ResetTwoFactorAction::NAME);
    }

    public function test_it_is_not_offered_for_ones_own_account(): void
    {
        $this->enable($this->admin);
        $this->actingAs($this->admin);

        $this->editPage($this->admin)->assertActionHidden(ResetTwoFactorAction::NAME);
    }

    public function test_it_is_not_offered_for_an_anonymized_account(): void
    {
        $this->actingAs($this->admin);
        $this->owner->forceFill(['anonymized_at' => now(), 'deleted_at' => now()])->save();

        $this->editPage()->assertActionHidden(ResetTwoFactorAction::NAME);
    }

    public function test_it_is_not_offered_without_the_permission(): void
    {
        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.view', 'users.update']));

        $this->editPage()->assertActionHidden(ResetTwoFactorAction::NAME);
    }

    public function test_the_permission_written_into_another_role_gives_nothing(): void
    {
        // Only the administrator role holds it: a stored copy in any other role is dropped
        $editor = $this->editorWithPermissions(['users.view_any', 'users.view', 'users.update', 'users.reset_two_factor']);
        $this->actingAs($editor);

        $this->editPage()->assertActionHidden(ResetTwoFactorAction::NAME);
        $this->assertFalse(Gate::forUser($editor)->allows('resetTwoFactor', $this->owner));
    }

    public function test_a_forged_call_without_the_permission_changes_nothing(): void
    {
        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.view', 'users.update']));
        Notification::fake();
        $log = Log::spy();

        // Straight to the Livewire methods, as a crafted request would: the hidden button is not needed
        $this->editPage()
            ->call('mountAction', ResetTwoFactorAction::NAME)
            ->set('mountedActions.0.data.confirmation', true)
            ->call('callMountedAction');

        $this->assertStillEnabled();
        Notification::assertNothingSent();
        $log->shouldNotHaveReceived('info', ['Two-factor reset from the panel', Mockery::any()]);
    }

    public function test_it_needs_the_box_ticked(): void
    {
        $this->actingAs($this->admin);

        $this->editPage()
            ->callAction(ResetTwoFactorAction::NAME, ['confirmation' => false])
            ->assertHasActionErrors(['confirmation']);

        $this->assertStillEnabled();
    }

    public function test_it_takes_two_factor_away_signs_the_account_out_and_tells_the_owner(): void
    {
        $this->actingAs($this->admin);
        $this->owner->refresh();
        $sealBefore = $this->owner->session_seal;
        $tokenBefore = $this->owner->remember_token;
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'owner-session', 'user_id' => $this->owner->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'Test', 'payload' => base64_encode(serialize([])), 'last_activity' => self::NOW,
        ]);
        Notification::fake();
        $log = Log::spy();

        $this->editPage()
            ->callAction(ResetTwoFactorAction::NAME, ['confirmation' => true])
            ->assertHasNoActionErrors()
            ->assertNotified(__('filament.users.actions.resetTwoFactor.done'));

        $this->assertNull(UserTwoFactorModel::query()->find($this->owner->id));
        $this->owner->refresh();
        $this->assertNotSame($sealBefore, $this->owner->session_seal);
        $this->assertNotSame($tokenBefore, $this->owner->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->owner->id)->count());
        Notification::assertSentTo($this->owner, TwoFactorResetNotification::class);
        $log->shouldHaveReceived('info')
            ->with('Two-factor reset from the panel', [
                'actor_id' => (string) $this->admin->id,
                'user_id' => (string) $this->owner->id,
            ])
            ->once();
    }

    public function test_the_log_holds_ids_only(): void
    {
        $this->actingAs($this->admin);
        $log = Log::spy();

        $this->editPage()->callAction(ResetTwoFactorAction::NAME, ['confirmation' => true]);

        $log->shouldHaveReceived('info')
            ->withArgs(static fn (string $message, array $context): bool => $message === 'Two-factor reset from the panel'
                && array_keys($context) === ['actor_id', 'user_id'])
            ->once();
    }

    public function test_it_says_so_when_there_was_nothing_to_take_away(): void
    {
        $this->actingAs($this->admin);
        // Turned off by someone else between the visibility check and the reset
        $reset = Mockery::mock(TwoFactorResetInterface::class);
        $reset->shouldReceive('reset')->once()->andReturnFalse();
        $this->app->instance(TwoFactorResetInterface::class, $reset);
        $log = Log::spy();

        $this->editPage()
            ->callAction(ResetTwoFactorAction::NAME, ['confirmation' => true])
            ->assertNotified(__('filament.users.actions.resetTwoFactor.nothing'));

        $log->shouldNotHaveReceived('info', ['Two-factor reset from the panel', Mockery::any()]);
    }

    public function test_the_action_goes_away_once_it_is_done(): void
    {
        $this->actingAs($this->admin);

        $this->editPage()
            ->callAction(ResetTwoFactorAction::NAME, ['confirmation' => true])
            ->assertActionHidden(ResetTwoFactorAction::NAME);
    }

    public function test_a_refusal_from_the_service_is_shown_and_changes_nothing(): void
    {
        $this->actingAs($this->admin);
        $reset = Mockery::mock(TwoFactorResetInterface::class);
        $reset->shouldReceive('reset')->once()->andThrow(TwoFactorException::because(TwoFactorFailure::Impersonating));
        $this->app->instance(TwoFactorResetInterface::class, $reset);
        $log = Log::spy();

        $this->editPage()
            ->callAction(ResetTwoFactorAction::NAME, ['confirmation' => true])
            ->assertNotified(__('filament.users.actions.resetTwoFactor.refused'));

        $this->assertStillEnabled();
        $log->shouldNotHaveReceived('info', ['Two-factor reset from the panel', Mockery::any()]);
    }

    public function test_the_policy_lets_the_administrator_reset_another_account(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('resetTwoFactor', $this->owner));
    }

    public function test_the_policy_never_lets_anyone_reset_their_own(): void
    {
        $this->assertFalse(Gate::forUser($this->admin)->allows('resetTwoFactor', $this->admin));
    }

    public function test_the_policy_never_lets_anyone_reset_an_anonymized_account(): void
    {
        $this->owner->forceFill(['anonymized_at' => now(), 'deleted_at' => now()])->save();

        $this->assertFalse(Gate::forUser($this->admin)->allows('resetTwoFactor', $this->owner->fresh()));
    }

    public function test_it_is_refused_while_impersonating_in_read_only_mode(): void
    {
        $this->actingAs($this->admin);
        $this->admin->forceFill(['name' => 'Marta Ejemplo', 'display_name' => 'Marta'])->save();
        app(ImpersonationContext::class)->activate(UserModel::factory()->admin()->create(), $this->admin, ImpersonationMode::ReadOnly, 'log-id');

        $this->editPage()
            ->callAction(ResetTwoFactorAction::NAME, ['confirmation' => true])
            ->assertNotified(__('filament.impersonation_read_only.refused', ['name' => 'Marta']));

        $this->assertStillEnabled();
    }

    /**
     * @return Testable<EditUser>
     */
    private function editPage(?UserModel $record = null): Testable
    {
        return Livewire::test(EditUser::class, ['record' => ($record ?? $this->owner)->getRouteKey()]);
    }

    private function assertStillEnabled(): void
    {
        $this->assertTrue(UserTwoFactorModel::query()->whereKey($this->owner->id)->whereNotNull('confirmed_at')->exists());
    }

    private function enable(UserModel $user): void
    {
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $user->id);
        $service->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));
    }
}
