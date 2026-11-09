<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Pause;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Domain\Mail\Enums\MailPauseDuration;
use App\Domain\Mail\Repositories\MailPauseRepositoryInterface;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Tests\Support\Mail\CategorizedTestNotification;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class MailPauseDeliveryTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    /**
     * @var list<string>
     */
    private array $sentCategories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MailCategoryRegistryInterface::class)->registerMany([
            MailCategoryDTO::mandatory(
                key: 'example.confirmations',
                section: 'Example section',
                label: 'Confirmations',
                description: 'What you did',
                reason: 'It confirms something you did',
            ),
            MailCategoryDTO::optional(
                key: 'example.reminders',
                section: 'Example section',
                label: 'Reminders',
                description: 'Before it starts',
            ),
        ], 'example');

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->notification instanceof CategorizedTestNotification) {
                $this->sentCategories[] = $event->notification->mailCategory();
            }
        });
    }

    public function test_a_paused_account_gets_no_optional_mail_but_still_gets_mandatory_mail(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->pause($user, MailPauseDuration::Indefinite);

        $user->notify(new CategorizedTestNotification('example.reminders'));
        $user->notify(new CategorizedTestNotification('example.confirmations'));

        $this->assertSame(['example.confirmations'], $this->sentCategories);
    }

    public function test_a_timed_pause_ends_by_itself_when_the_date_passes(): void
    {
        $this->travelTo('2026-12-01 10:00:00');
        $user = UserModel::factory()->create();
        $this->service()->pause($user, MailPauseDuration::Week);

        $this->travelTo('2026-12-08 12:00:00');
        $user->notify(new CategorizedTestNotification('example.reminders'));
        $this->assertSame([], $this->sentCategories);

        // The pause lasts to the end of the display day: the 8th, inclusive
        $this->travelTo('2026-12-09 12:00:00');
        $user->notify(new CategorizedTestNotification('example.reminders'));
        $this->assertSame(['example.reminders'], $this->sentCategories);
        $this->assertNull($this->service()->current($user));
    }

    public function test_the_pause_never_touches_the_category_choices(): void
    {
        $user = UserModel::factory()->create();
        $preferences = app(MailPreferenceRepositoryInterface::class);
        $preferences->save(UserId::fromString($user->id), ['example.reminders' => false]);

        $this->service()->pause($user, MailPauseDuration::Month);
        $this->service()->resume($user);

        $this->assertSame(['example.reminders'], $preferences->disabledFor(UserId::fromString($user->id)));
    }

    public function test_resuming_lets_optional_mail_through_again(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->pause($user, MailPauseDuration::Indefinite);
        $this->service()->resume($user);

        $user->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertSame(['example.reminders'], $this->sentCategories);
    }

    public function test_a_pause_that_cannot_be_read_lets_the_mail_through(): void
    {
        $user = UserModel::factory()->create();
        $repository = Mockery::mock(MailPauseRepositoryInterface::class);
        $repository->shouldReceive('find')->andThrow(new RuntimeException('Database down'));
        $this->app->instance(MailPauseRepositoryInterface::class, $repository);

        $user->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertSame(['example.reminders'], $this->sentCategories);
    }

    public function test_only_the_paused_account_is_held_back(): void
    {
        $paused = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->service()->pause($paused, MailPauseDuration::Indefinite);

        $other->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertSame(['example.reminders'], $this->sentCategories);
    }

    public function test_with_utc_storage_a_week_ends_with_the_madrid_day_and_is_stored_in_utc(): void
    {
        $this->useUtcStorage();
        config(['app.display_timezone' => 'Europe/Madrid']);
        // 23:30 UTC on 1 Dec is already 2 Dec in Madrid
        $this->travelTo('2026-12-01 23:30:00');
        $user = UserModel::factory()->create();

        $this->service()->pause($user, MailPauseDuration::Week);

        // Madrid day 2 Dec + 7 = 9 Dec; it ends at the start of 10 Dec Madrid = 23:00 UTC on the 9th
        $this->assertSame(
            '2026-12-09 23:00:00',
            (string) DB::table('user_mail_pauses')->where('user_id', $user->id)->value('paused_until'),
        );

        $this->travelTo('2026-12-09 22:59:00');
        $this->assertNotNull($this->service()->current($user));
        $this->travelTo('2026-12-09 23:00:00');
        $this->assertNull($this->service()->current($user));
    }

    public function test_a_month_does_not_overflow_the_end_of_a_short_month(): void
    {
        $this->useUtcStorage();
        config(['app.display_timezone' => 'UTC']);
        $this->travelTo('2027-01-31 10:00:00');
        $user = UserModel::factory()->create();

        $pause = $this->service()->pause($user, MailPauseDuration::Month);

        $this->assertSame('2027-03-01 00:00:00', $pause->until?->format('Y-m-d H:i:s'));
    }

    public function test_pausing_again_replaces_the_previous_pause(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->pause($user, MailPauseDuration::Month);
        $this->service()->pause($user, MailPauseDuration::Indefinite);

        $this->assertSame(1, DB::table('user_mail_pauses')->count());
        $this->assertNull($this->service()->current($user)?->until);
        $this->assertNotNull($this->service()->current($user));
    }

    private function service(): MailPauseServiceInterface
    {
        return $this->app->make(MailPauseServiceInterface::class);
    }
}
