<?php

declare(strict_types=1);

namespace Tests\Feature\SiteNotifications;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SiteNotificationModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

/**
 * How long notices stay: read ones 90 days, unread ones 180, nothing once the account is
 * anonymized, and nothing about a thing that was deleted.
 */
final class SiteNotificationRetentionTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useUtcStorage();

        $this->app->make(MailCategoryRegistryInterface::class)->register(
            MailCategoryDTO::optional(key: 'example.replies', section: 'Example', label: 'Replies', description: 'D', site: true),
            'example',
        );
    }

    public function test_the_prune_command_drops_old_read_and_older_unread_notices(): void
    {
        $user = UserModel::factory()->create();
        $this->travelTo(now()->subDays(200));
        $this->notify($user, '/viejo-sin-leer');
        $this->travelBack();
        $this->travelTo(now()->subDays(100));
        $this->notify($user, '/sin-leer');
        $this->notify($user, '/leido-hace-100');
        $this->open($user, '/leido-hace-100');
        $this->travelBack();
        $this->travelTo(now()->subDays(10));
        $this->notify($user, '/leido-hace-10');
        $this->open($user, '/leido-hace-10');
        $this->travelBack();

        $command = $this->artisan('site-notifications:prune');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('Pruned: 2')->assertSuccessful();
        $command->run();

        $this->assertEqualsCanonicalizing(
            ['/sin-leer', '/leido-hace-10'],
            SiteNotificationModel::query()->pluck('url')->all(),
        );
    }

    public function test_the_prune_command_runs_daily(): void
    {
        $events = array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'site-notifications:prune'),
        );

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', array_first($events)->expression);
    }

    public function test_anonymizing_an_account_deletes_its_notices_and_site_choices(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->notify($user, '/a');
        $this->notify($other, '/b');
        $this->app->make(SiteNotificationPreferenceServiceInterface::class)->update($user, ['example.replies' => false]);

        $this->app->make(UserServiceInterface::class)->anonymize($user->id);

        $this->assertSame(0, SiteNotificationModel::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, SiteNotificationModel::query()->where('user_id', $other->id)->count());
        $this->assertDatabaseCount('user_site_notification_preferences', 0);
    }

    public function test_forgetting_a_subject_deletes_its_notices_for_everybody(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $topic = new MailSubjectDTO('example.topic', 'abc', 'La tumba');
        $this->notify($user, '/foro/abc', $topic);
        $this->notify($other, '/foro/abc', $topic);
        $this->notify($user, '/foro/otro', new MailSubjectDTO('example.topic', 'other', 'Otro'));

        $this->app->make(MailSubjectMuteServiceInterface::class)->forgetSubject('example.topic', 'abc');

        $this->assertSame(['/foro/otro'], SiteNotificationModel::query()->pluck('url')->all());
    }

    private function notify(UserModel $user, string $url, ?MailSubjectDTO $subject = null): void
    {
        $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: 'Aviso', url: $url), $subject));
    }

    private function open(UserModel $user, string $url): void
    {
        $id = SiteNotificationModel::query()->where('user_id', $user->id)->where('url', $url)->value('id');
        $this->app->make(SiteNotificationServiceInterface::class)->open($user, (string) $id);
    }
}
