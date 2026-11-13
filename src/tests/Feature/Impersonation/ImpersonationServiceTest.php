<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Events\UserLoggedIn;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Impersonation\Enums\ImpersonationRefusal;
use App\Domain\Impersonation\Exceptions\ImpersonationNotAllowedException;
use App\Infrastructure\Impersonation\ImpersonationSessionState;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Starting keeps the administrator's own session: the state goes into it, a log entry opens
 * and the session id changes. Nobody signs in. Stopping closes the entry and drops the state.
 */
final class ImpersonationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_starting_stores_the_state_in_the_session_and_opens_the_log(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create();
        $this->actingAs($admin);
        $session = $this->sessionStore();
        $idBefore = $session->getId();
        $this->app['request']->server->set('REMOTE_ADDR', '203.0.113.7');
        $this->app['request']->headers->set('User-Agent', 'Example browser');

        $this->service()->start($admin, $member, ImpersonationMode::Simulate);

        $entry = ImpersonationLogEntryModel::query()->sole();
        $this->assertSame($admin->id, $entry->impersonator_id);
        $this->assertSame($member->id, $entry->target_id);
        $this->assertSame(ImpersonationMode::Simulate, $entry->mode);
        $this->assertTrue($entry->started_at->isToday());
        $this->assertNull($entry->ended_at);
        $this->assertNull($entry->ended_reason);
        $this->assertSame('203.0.113.7', $entry->ip);
        $this->assertSame('Example browser', $entry->user_agent);

        $this->assertSame([
            'target_id' => $member->id,
            'log_id' => $entry->id,
            'mode' => 'simulate',
            'impersonator_id' => $admin->id,
        ], $session->get(ImpersonationSessionState::SESSION_KEY));
        $this->assertNotSame($idBefore, $session->getId());
        // The session still belongs to the administrator
        $this->assertTrue(Auth::user()?->is($admin));
    }

    public function test_starting_signs_nobody_in(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create();
        $this->actingAs($admin);
        Event::fake([Login::class, Authenticated::class, UserLoggedIn::class]);

        $this->service()->start($admin, $member, ImpersonationMode::ReadOnly);

        Event::assertNotDispatched(Login::class);
        Event::assertNotDispatched(Authenticated::class);
        Event::assertNotDispatched(UserLoggedIn::class);
    }

    public function test_starting_is_refused_for_an_account_that_cannot_be_impersonated(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $otherAdmin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        try {
            $this->service()->start($admin, $otherAdmin, ImpersonationMode::ReadOnly);
            $this->fail('An administrator was impersonated');
        } catch (ImpersonationNotAllowedException $exception) {
            $this->assertSame(ImpersonationRefusal::TargetIsAdmin, $exception->refusal);
        }

        $this->assertSame(0, ImpersonationLogEntryModel::query()->count());
        $this->assertFalse($this->sessionStore()->has(ImpersonationSessionState::SESSION_KEY));
    }

    public function test_starting_is_refused_for_someone_who_is_not_an_administrator(): void
    {
        $editor = UserModel::factory()->editor()->create();
        $member = UserModel::factory()->create();
        $this->actingAs($editor);

        $this->expectExceptionObject(new ImpersonationNotAllowedException(ImpersonationRefusal::NotPermitted));

        $this->service()->start($editor, $member, ImpersonationMode::ReadOnly);
    }

    public function test_a_second_impersonation_cannot_start(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        $this->service()->start($admin, UserModel::factory()->create(), ImpersonationMode::ReadOnly);

        try {
            $this->service()->start($admin, UserModel::factory()->create(), ImpersonationMode::ReadOnly);
            $this->fail('A second impersonation started');
        } catch (ImpersonationNotAllowedException $exception) {
            $this->assertSame(ImpersonationRefusal::AlreadyImpersonating, $exception->refusal);
        }

        $this->assertSame(1, ImpersonationLogEntryModel::query()->count());
    }

    public function test_stopping_closes_the_log_and_drops_the_state(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        $this->service()->start($admin, UserModel::factory()->create(), ImpersonationMode::ReadOnly);
        $idBefore = $this->sessionStore()->getId();

        $this->service()->stop(ImpersonationEndReason::Stopped);

        $entry = ImpersonationLogEntryModel::query()->sole();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(ImpersonationEndReason::Stopped, $entry->ended_reason);
        $this->assertFalse($this->sessionStore()->has(ImpersonationSessionState::SESSION_KEY));
        $this->assertNotSame($idBefore, $this->sessionStore()->getId());
        $this->assertTrue(Auth::user()?->is($admin));
    }

    public function test_stopping_without_an_impersonation_does_nothing(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        $idBefore = $this->sessionStore()->getId();

        $this->service()->stop(ImpersonationEndReason::Stopped);

        $this->assertSame($idBefore, $this->sessionStore()->getId());
        $this->assertSame(0, ImpersonationLogEntryModel::query()->count());
    }

    private function service(): ImpersonationServiceInterface
    {
        return app(ImpersonationServiceInterface::class);
    }

    private function sessionStore(): Session
    {
        return app(Session::class);
    }
}
