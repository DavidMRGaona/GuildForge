<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Domain\Profiles\Exceptions\ProfileHandleUnavailableException;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Another account taking an address between the check and the write, forced without threads: right after
 * the service reads the history for an address, a listener writes that address for somebody else. Each
 * save runs inside an outer transaction, as the panel's user form does, and keeps going after the clash
 * (in PostgreSQL a failed statement outside a savepoint would abort it).
 */
final class UserProfileClashTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->other = UserModel::factory()->create(['display_name' => null]);
    }

    public function test_a_new_account_takes_the_next_address_when_its_own_is_taken_meanwhile(): void
    {
        $this->takeRightAfterTheCheck(['ana']);

        $user = DB::transaction(static function (): UserModel {
            $user = UserModel::factory()->create(['name' => 'Ana López', 'display_name' => 'Ana']);
            // The transaction is still usable after the clash
            $user->update(['name' => 'Ana Ruiz']);

            return $user;
        });

        $this->assertSame('ana-2', UserProfileModel::query()->findOrFail($user->id)->handle);
        $this->assertSame('Ana Ruiz', $user->fresh()?->name);
        $this->assertSame((string) $this->other->id, UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('ana'))->user_id);
    }

    public function test_a_new_public_name_takes_the_next_address_when_its_own_is_taken_meanwhile(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->takeRightAfterTheCheck(['bea']);

        DB::transaction(static function () use ($user): void {
            $user->update(['display_name' => 'Bea']);
            $user->update(['name' => 'Bea Ruiz']);
        });

        $this->assertSame('bea-2', UserProfileModel::query()->findOrFail($user->id)->handle);
        $this->assertSame('Bea Ruiz', $user->fresh()?->name);
        $bea = UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('bea'));
        $this->assertSame((string) $this->other->id, $bea->user_id);
        $this->assertNull($bea->retired_at);
        $ana = UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('ana'));
        $this->assertSame((string) $user->id, $ana->user_id);
        $this->assertNotNull($ana->retired_at);
    }

    public function test_without_any_free_address_the_service_says_so(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        UserProfileModel::query()->whereKey($user->id)->delete();
        UserProfileHandleModel::query()->where('user_id', $user->id)->delete();
        $this->takeRightAfterTheCheck(null);

        $this->expectException(ProfileHandleUnavailableException::class);

        app(UserProfileServiceInterface::class)->createFor((string) $user->id);
    }

    public function test_without_any_free_address_the_account_is_still_created_and_the_error_logged(): void
    {
        $this->takeRightAfterTheCheck(null);
        $log = Log::spy();

        $user = DB::transaction(static function (): UserModel {
            $user = UserModel::factory()->create(['display_name' => 'Ana']);
            $user->update(['name' => 'Ana Ruiz']);

            return $user;
        });

        $this->assertSame('Ana Ruiz', $user->fresh()?->name);
        $this->assertNull(UserProfileModel::query()->find($user->id));
        $log->shouldHaveReceived('error')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['user_id'] === (string) $user->id
                && $context['exception'] instanceof ProfileHandleUnavailableException,
        );
    }

    public function test_creating_the_missing_profiles_counts_the_ones_that_could_not_be(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $stale = UserModel::factory()->create(['display_name' => 'Bea']);
        UserProfileModel::query()->whereKey($user->id)->delete();
        UserProfileHandleModel::query()->where('user_id', $user->id)->delete();
        DB::table('users')->where('id', $stale->id)->update(['display_name' => 'Carla']);
        $this->takeRightAfterTheCheck(null);
        Log::spy();

        $result = app(UserProfileServiceInterface::class)->createMissing(dryRun: false, chunk: 500);

        $this->assertSame(0, $result->created);
        $this->assertSame(0, $result->realigned);
        $this->assertSame(2, $result->failed);
        $this->assertNull(UserProfileModel::query()->find($user->id));
        $this->assertSame('bea', UserProfileModel::query()->findOrFail($stale->id)->handle);
    }

    /**
     * @param  list<string>|null  $handles  the addresses to take, or null for every address checked
     */
    private function takeRightAfterTheCheck(?array $handles): void
    {
        $hashes = $handles === null ? null : array_map(ProfileHandle::hashOf(...), $handles);
        $otherId = (string) $this->other->id;
        $taken = [];

        DB::listen(static function (QueryExecuted $query) use ($hashes, $otherId, &$taken): void {
            $hash = $query->bindings[0] ?? null;

            // Its own insert is not a select, so it never comes back here
            if (! is_string($hash)
                || preg_match('/^[0-9a-f]{64}$/', $hash) !== 1
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains($query->sql, 'user_profile_handles')
                || isset($taken[$hash])
                || ($hashes !== null && ! in_array($hash, $hashes, true))) {
                return;
            }

            $taken[$hash] = true;
            DB::table('user_profile_handles')->insert(['handle_hash' => $hash, 'handle' => null, 'user_id' => $otherId, 'retired_at' => null]);
        });
    }
}
