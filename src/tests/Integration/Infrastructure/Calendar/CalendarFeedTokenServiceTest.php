<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Calendar;

use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CalendarFeedTokenServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CalendarFeedTokenServiceInterface $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokens = app(CalendarFeedTokenServiceInterface::class);
    }

    public function test_an_issued_token_is_long_url_safe_and_finds_its_account(): void
    {
        $user = UserModel::factory()->create();

        $token = $this->tokens->issue($user->id);

        // 32 random bytes in base64url without padding
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
        $this->assertSame($user->id, $this->tokens->userIdFor($token));
    }

    public function test_only_a_hash_of_the_token_is_stored(): void
    {
        $user = UserModel::factory()->create();

        $token = $this->tokens->issue($user->id);

        $row = (array) DB::table('calendar_feed_tokens')->where('user_id', $user->id)->sole();
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->assertNotContains($token, array_map(static fn (mixed $value): string => (string) $value, $row));
    }

    public function test_issuing_again_replaces_the_previous_token(): void
    {
        $user = UserModel::factory()->create();
        $old = $this->tokens->issue($user->id);

        $new = $this->tokens->issue($user->id);

        $this->assertNotSame($old, $new);
        $this->assertNull($this->tokens->userIdFor($old));
        $this->assertSame($user->id, $this->tokens->userIdFor($new));
        $this->assertSame(1, DB::table('calendar_feed_tokens')->where('user_id', $user->id)->count());
    }

    public function test_a_revoked_token_finds_nothing(): void
    {
        $user = UserModel::factory()->create();
        $token = $this->tokens->issue($user->id);

        $this->tokens->revoke($user->id);

        $this->assertNull($this->tokens->userIdFor($token));
        $this->assertNull($this->tokens->issuedAt($user->id));
    }

    public function test_revoking_without_a_token_does_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->tokens->revoke($user->id);

        $this->assertNull($this->tokens->issuedAt($user->id));
    }

    public function test_it_remembers_when_the_token_was_issued(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-11-07 18:30:00 UTC'));
        $user = UserModel::factory()->create();

        $this->tokens->issue($user->id);

        $this->assertEquals(new DateTimeImmutable('2026-11-07 18:30:00 UTC'), $this->tokens->issuedAt($user->id));
    }

    public function test_unknown_or_malformed_tokens_find_nothing(): void
    {
        UserModel::factory()->create();

        $this->assertNull($this->tokens->userIdFor(str_repeat('a', 43)));
        $this->assertNull($this->tokens->userIdFor(''));
        $this->assertNull($this->tokens->userIdFor('short'));
        $this->assertNull($this->tokens->userIdFor(str_repeat('a', 500)));
    }

    public function test_deleting_the_account_deletes_its_token(): void
    {
        $user = UserModel::factory()->create();
        $this->tokens->issue($user->id);

        $user->forceDelete();

        $this->assertSame(0, DB::table('calendar_feed_tokens')->count());
    }
}
