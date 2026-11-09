<?php

declare(strict_types=1);

namespace Tests\Integration\Mail;

use App\Domain\Mail\Enums\MailDelivery;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EloquentMailPreferenceRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_without_rows_nothing_is_disabled(): void
    {
        $user = UserModel::factory()->create();

        $this->assertSame([], $this->repository()->disabledFor(UserId::fromString($user->id)));
    }

    public function test_it_saves_and_updates_one_row_per_category(): void
    {
        $user = UserModel::factory()->create();
        $userId = UserId::fromString($user->id);

        $this->repository()->save($userId, ['example.first' => false, 'example.second' => false]);
        $this->repository()->save($userId, ['example.second' => true]);

        $this->assertSame(['example.first'], $this->repository()->disabledFor($userId));
        $this->assertSame(2, DB::table('user_mail_preferences')->where('user_id', $user->id)->count());
    }

    public function test_preferences_belong_to_one_account(): void
    {
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();

        $this->repository()->save(UserId::fromString($first->id), ['example.first' => false]);

        $this->assertSame([], $this->repository()->disabledFor(UserId::fromString($second->id)));
    }

    public function test_it_deletes_the_rows_of_an_account(): void
    {
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();
        $this->repository()->save(UserId::fromString($first->id), ['example.first' => false]);
        $this->repository()->save(UserId::fromString($second->id), ['example.first' => false]);

        $this->repository()->deleteForUser(UserId::fromString($first->id));

        $this->assertSame(0, DB::table('user_mail_preferences')->where('user_id', $first->id)->count());
        $this->assertSame(['example.first'], $this->repository()->disabledFor(UserId::fromString($second->id)));
    }

    public function test_deleting_the_account_deletes_its_rows(): void
    {
        $user = UserModel::factory()->create();
        $this->repository()->save(UserId::fromString($user->id), ['example.first' => false]);

        $user->forceDelete();

        $this->assertSame(0, DB::table('user_mail_preferences')->count());
    }

    public function test_it_saves_the_three_deliveries_in_the_same_row(): void
    {
        $user = UserModel::factory()->create();
        $userId = UserId::fromString($user->id);

        $this->repository()->save($userId, ['example.first' => MailDelivery::Digest, 'example.second' => MailDelivery::Digest]);
        $this->assertSame(['example.first', 'example.second'], $this->repository()->digestFor($userId));
        $this->assertSame([], $this->repository()->disabledFor($userId));

        $this->repository()->save($userId, ['example.first' => MailDelivery::None, 'example.second' => MailDelivery::Immediate]);
        $this->assertSame([], $this->repository()->digestFor($userId));
        $this->assertSame(['example.first'], $this->repository()->disabledFor($userId));
        $this->assertSame(2, DB::table('user_mail_preferences')->where('user_id', $user->id)->count());
    }

    private function repository(): MailPreferenceRepositoryInterface
    {
        return $this->app->make(MailPreferenceRepositoryInterface::class);
    }
}
