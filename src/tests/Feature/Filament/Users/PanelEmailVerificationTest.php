<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\VerifyPendingEmailNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An address only counts as verified once a link sent to it has been followed. The panel must
 * not get around that: changing an account's email there goes through the same pending change
 * as the profile page, and an account created there starts unverified unless whoever creates it
 * says otherwise, which is logged.
 */
final class PanelEmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->actingAs($this->admin);
        Notification::fake();
    }

    // Editing an account

    public function test_changing_the_email_keeps_the_address_and_its_verification_until_the_link_is_followed(): void
    {
        $user = UserModel::factory()->create(['email' => 'old@example.com']);
        $verifiedAt = $user->email_verified_at;

        $this->editEmail($user, 'new@example.com');

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertEquals($verifiedAt, $user->email_verified_at);
    }

    public function test_changing_the_email_sends_the_confirmation_link_to_the_new_address(): void
    {
        $user = UserModel::factory()->create(['email' => 'old@example.com']);

        $this->editEmail($user, 'new@example.com');

        Notification::assertSentTo(
            $user,
            VerifyPendingEmailNotification::class,
            static fn (VerifyPendingEmailNotification $notification): bool => $notification->mailRecipient() === 'new@example.com',
        );
    }

    public function test_changing_the_email_of_an_unverified_account_leaves_it_unverified(): void
    {
        $user = UserModel::factory()->unverified()->create(['email' => 'old@example.com']);

        $this->editEmail($user, 'new@example.com');

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_changing_the_email_logs_who_requested_it_without_the_addresses(): void
    {
        $user = UserModel::factory()->create(['email' => 'old@example.com']);
        $log = Log::spy();

        $this->editEmail($user, 'new@example.com');

        $log->shouldHaveReceived('info')
            ->with('Account email change requested from the panel', [
                'actor_id' => (string) $this->admin->id,
                'user_id' => (string) $user->id,
            ])
            ->once();
    }

    public function test_saving_other_fields_keeps_a_change_the_person_requested_and_sends_nothing(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'requested@example.com',
        ]);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['display_name' => 'Another name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('requested@example.com', $user->pending_email);
        $this->assertSame('Another name', $user->display_name);
        Notification::assertNothingSent();
    }

    public function test_submitting_the_pending_address_again_sends_no_new_link(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);

        $this->editEmail($user, 'new@example.com');

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);
        Notification::assertNothingSent();
    }

    public function test_the_new_address_must_not_belong_to_another_account(): void
    {
        UserModel::factory()->create(['email' => 'taken@example.com']);
        $user = UserModel::factory()->create(['email' => 'old@example.com']);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['email' => 'taken@example.com'])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertNull($user->refresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_a_refused_role_change_saves_no_email_change_and_sends_nothing(): void
    {
        $treasurer = RoleModel::query()->create(['name' => 'treasurer', 'display_name' => 'Treasurer', 'max_holders' => 1]);
        UserModel::factory()->create()->roles()->attach($treasurer->id);
        $user = UserModel::factory()->create(['email' => 'old@example.com']);
        $log = Log::spy();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['email' => 'new@example.com', 'roles' => ['member', 'treasurer']])
            ->call('save');

        $this->assertNull($user->refresh()->pending_email);
        Notification::assertNothingSent();
        $log->shouldNotHaveReceived('info', ['Account email change requested from the panel', \Mockery::any()]);
    }

    public function test_after_saving_the_field_shows_the_current_address_again(): void
    {
        $user = UserModel::factory()->create(['email' => 'old@example.com']);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['email' => 'new@example.com'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet(['email' => 'old@example.com']);
    }

    public function test_a_pending_change_can_be_withdrawn_and_it_is_logged(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'wrong@example.com',
        ]);
        $log = Log::spy();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertActionVisible('cancelPendingEmail')
            ->callAction('cancelPendingEmail');

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $log->shouldHaveReceived('info')
            ->with('Pending account email change cancelled from the panel', [
                'actor_id' => (string) $this->admin->id,
                'user_id' => (string) $user->id,
            ])
            ->once();
    }

    public function test_withdrawing_is_only_offered_while_a_change_is_pending(): void
    {
        $user = UserModel::factory()->create(['pending_email' => null]);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertActionHidden('cancelPendingEmail');
    }

    // Creating an account

    public function test_an_account_created_in_the_panel_starts_unverified_and_gets_the_verification_link(): void
    {
        $this->createAccount([]);

        $user = UserModel::query()->where('email', 'created@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_an_account_created_in_the_panel_can_be_marked_as_verified_on_purpose(): void
    {
        $log = Log::spy();

        $this->createAccount(['mark_email_verified' => true]);

        $user = UserModel::query()->where('email', 'created@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        Notification::assertNotSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, WelcomeNotification::class);
        $log->shouldHaveReceived('info')
            ->with('Account email marked as verified from the panel', [
                'actor_id' => (string) $this->admin->id,
                'user_id' => (string) $user->id,
            ])
            ->once();
    }

    public function test_the_verified_mark_is_only_offered_when_creating(): void
    {
        $user = UserModel::factory()->unverified()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertFormFieldDoesNotExist('mark_email_verified')
            ->fillForm(['mark_email_verified' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    private function editEmail(UserModel $user, string $email): void
    {
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['email' => $email])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createAccount(array $extra): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'created',
                'email' => 'created@example.com',
                'password' => 'a-long-password',
                ...$extra,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
