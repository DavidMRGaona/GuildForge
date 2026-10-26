<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Authorization\Services;

use App\Domain\Authorization\Enums\RoleAuditSource;
use App\Infrastructure\Authorization\Services\RoleAuditContext;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class RoleAuditContextTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string ACTOR = '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b';

    public function test_without_anything_imposed_it_reports_the_signed_in_user_and_the_resolved_source(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $this->assertSame($user->id, $this->context()->actorId());
        $this->assertSame(RoleAuditSource::Command, $this->context()->source());
    }

    public function test_acting_for_imposes_the_actor_and_the_source_only_inside_the_callback(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $inside = $this->context()->actingFor(
            self::ACTOR,
            fn (): array => [$this->context()->actorId(), $this->context()->source()],
            RoleAuditSource::Queue,
        );

        $this->assertSame([self::ACTOR, RoleAuditSource::Queue], $inside);
        $this->assertNotSame(self::ACTOR, $this->context()->actorId());
        $this->assertSame(RoleAuditSource::Command, $this->context()->source());
    }

    public function test_an_imposed_null_actor_hides_the_signed_in_user(): void
    {
        // A queued update nobody requested is not attributed to whoever the worker last saw
        $this->actingAs(UserModel::factory()->create());

        $this->assertNull($this->context()->actingFor(null, fn (): ?string => $this->context()->actorId()));
    }

    public function test_without_a_source_the_resolved_one_is_kept(): void
    {
        $this->assertSame(
            RoleAuditSource::Command,
            $this->context()->actingFor(self::ACTOR, fn (): RoleAuditSource => $this->context()->source()),
        );
    }

    public function test_the_previous_context_comes_back_when_the_callback_throws(): void
    {
        try {
            $this->context()->actingFor(self::ACTOR, static function (): never {
                throw new RuntimeException('update failed');
            }, RoleAuditSource::Queue);
        } catch (RuntimeException) {
        }

        // The queue worker runs the next job with the same instance
        $this->assertNull($this->context()->actorId());
        $this->assertSame(RoleAuditSource::Command, $this->context()->source());
    }

    public function test_nested_calls_restore_the_outer_context(): void
    {
        $outer = $this->context()->actingFor(self::ACTOR, function (): ?string {
            $this->context()->actingFor(null, static fn (): null => null, RoleAuditSource::System);

            return $this->context()->actorId();
        }, RoleAuditSource::Queue);

        $this->assertSame(self::ACTOR, $outer);
    }

    private function context(): RoleAuditContext
    {
        return $this->app->make(RoleAuditContext::class);
    }
}
