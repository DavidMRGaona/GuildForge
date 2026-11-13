<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationAccountsInterface;
use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Impersonation\ImpersonationSessionState;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * On every request the administrator's session is checked again: while the impersonation
 * holds, the request runs as the other account; as soon as it does not, or a check fails,
 * the request runs as the administrator and the entry is closed as revoked.
 */
final class ApplyImpersonationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    private UserModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/impersonation/whoami', static fn (ImpersonationContextInterface $context): array => [
            'user' => Auth::id(),
            'active' => $context->isActive(),
            'impersonator' => $context->impersonator()?->getAuthIdentifier(),
            'target' => $context->target()?->getAuthIdentifier(),
            'mode' => $context->mode()?->value,
        ]);

        $this->admin = UserModel::factory()->admin()->create();
        $this->member = UserModel::factory()->create();
    }

    public function test_a_request_runs_as_the_impersonated_account(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->getJson('/_test/impersonation/whoami')->assertExactJson([
            'user' => $this->member->id,
            'active' => true,
            'impersonator' => $this->admin->id,
            'target' => $this->member->id,
            'mode' => 'simulate',
        ]);
    }

    public function test_the_identity_only_lasts_for_the_request(): void
    {
        $this->startImpersonating();

        $this->getJson('/_test/impersonation/whoami')->assertJsonPath('user', $this->member->id);

        // The guard holds the administrator again, so the next request checks from them
        $this->assertTrue(Auth::user()?->is($this->admin));
        $this->getJson('/_test/impersonation/whoami')->assertJsonPath('user', $this->member->id);
        $this->assertSame(1, ImpersonationLogEntryModel::query()->whereNull('ended_at')->count());
    }

    public function test_without_an_impersonation_the_request_runs_as_the_account_signed_in(): void
    {
        $this->actingAs($this->admin);

        $this->getJson('/_test/impersonation/whoami')->assertExactJson([
            'user' => $this->admin->id,
            'active' => false,
            'impersonator' => null,
            'target' => null,
            'mode' => null,
        ]);
    }

    public function test_it_is_revoked_when_the_target_becomes_an_administrator(): void
    {
        $this->startImpersonating();
        app(AuthorizationServiceInterface::class)->assignRole($this->member, 'admin');

        $this->assertRevokedOnTheNextRequest();
    }

    public function test_it_is_revoked_when_the_target_is_deactivated(): void
    {
        $this->startImpersonating();
        $this->member->delete();

        $this->assertRevokedOnTheNextRequest();
    }

    public function test_it_is_revoked_when_the_target_is_anonymized(): void
    {
        $this->startImpersonating();
        $this->member->forceFill(['anonymized_at' => now()])->save();

        $this->assertRevokedOnTheNextRequest();
    }

    public function test_it_is_revoked_when_the_target_no_longer_exists(): void
    {
        $this->startImpersonating();
        $this->member->forceDelete();

        $this->assertRevokedOnTheNextRequest();
    }

    public function test_it_is_revoked_when_the_administrator_loses_the_role(): void
    {
        $this->startImpersonating();
        // A second administrator keeps the role in use; the first one loses it
        UserModel::factory()->admin()->create();
        app(AuthorizationServiceInterface::class)->removeRole($this->admin, 'admin');

        $this->assertRevokedOnTheNextRequest();
    }

    public function test_it_is_revoked_when_the_session_belongs_to_someone_else(): void
    {
        $this->startImpersonating();
        $other = UserModel::factory()->admin()->create();
        $this->actingAs($other);

        $response = $this->getJson('/_test/impersonation/whoami');

        $response->assertJsonPath('user', $other->id)->assertJsonPath('active', false);
        $this->assertSame(ImpersonationEndReason::Revoked, ImpersonationLogEntryModel::query()->sole()->ended_reason);
    }

    public function test_it_is_revoked_when_a_check_throws(): void
    {
        $this->startImpersonating();
        $accounts = $this->createStub(ImpersonationAccountsInterface::class);
        $accounts->method('find')->willThrowException(new RuntimeException('database down'));
        $this->app->instance(ImpersonationAccountsInterface::class, $accounts);

        $this->assertRevokedOnTheNextRequest();
    }

    public function test_a_malformed_state_is_dropped_and_the_request_runs_as_the_administrator(): void
    {
        $this->actingAs($this->admin);
        app(Session::class)->put(ImpersonationSessionState::SESSION_KEY, ['target_id' => $this->member->id]);

        $this->getJson('/_test/impersonation/whoami')
            ->assertJsonPath('user', $this->admin->id)
            ->assertJsonPath('active', false);

        $this->assertFalse(app(Session::class)->has(ImpersonationSessionState::SESSION_KEY));
    }

    public function test_the_impersonated_account_without_panel_access_gets_no_panel(): void
    {
        $this->startImpersonating();

        // The identity is applied before the panel checks access: never the administrator's.
        // Not a 403 without band: the site, which has it, with a notice
        $this->get('/admin')->assertRedirect(route('home'));
    }

    public function test_the_impersonated_account_with_panel_access_sees_the_panel(): void
    {
        $editor = UserModel::factory()->editor()->create();
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->start($this->admin, $editor, ImpersonationMode::ReadOnly);

        $this->get('/admin')->assertOk();
    }

    public function test_a_panel_request_is_revoked_like_a_site_request(): void
    {
        $this->startImpersonating();
        $this->member->delete();

        // Back to the administrator, who may open the panel
        $this->get('/admin')->assertOk();
        $this->assertSame(ImpersonationEndReason::Revoked, ImpersonationLogEntryModel::query()->sole()->ended_reason);
    }

    private function startImpersonating(ImpersonationMode $mode = ImpersonationMode::ReadOnly): void
    {
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->start($this->admin, $this->member, $mode);
    }

    private function assertRevokedOnTheNextRequest(): void
    {
        $this->getJson('/_test/impersonation/whoami')
            ->assertJsonPath('user', $this->admin->id)
            ->assertJsonPath('active', false);

        $entry = ImpersonationLogEntryModel::query()->sole();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(ImpersonationEndReason::Revoked, $entry->ended_reason);
        $this->assertFalse(app(Session::class)->has(ImpersonationSessionState::SESSION_KEY));
        $this->assertInstanceOf(Authenticatable::class, Auth::user());
    }
}
