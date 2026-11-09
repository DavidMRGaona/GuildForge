<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Preferences;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\Support\Mail\CategorizedTestNotification;
use Tests\Support\Mail\UncategorizedTestNotification;
use Tests\TestCase;

final class FilterMailByPreferencesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var list<class-string>
     */
    private array $sent = [];

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
            $this->sent[] = $event->notification::class;
        });
    }

    public function test_a_disabled_optional_category_is_not_sent(): void
    {
        $user = UserModel::factory()->create();
        $this->disable($user, 'example.reminders');

        $user->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertSame([], $this->sent);
    }

    public function test_a_mandatory_category_is_always_sent(): void
    {
        $user = UserModel::factory()->create();
        $this->disable($user, 'example.confirmations');

        $user->notify(new CategorizedTestNotification('example.confirmations'));

        $this->assertSame([CategorizedTestNotification::class], $this->sent);
    }

    public function test_without_a_preference_the_mail_is_sent(): void
    {
        UserModel::factory()->create()->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertSame([CategorizedTestNotification::class], $this->sent);
    }

    public function test_preferences_that_cannot_be_read_let_the_mail_through(): void
    {
        $user = UserModel::factory()->create();
        $repository = Mockery::mock(MailPreferenceRepositoryInterface::class);
        $repository->shouldReceive('disabledFor')->andThrow(new RuntimeException('Database down'));
        $this->app->instance(MailPreferenceRepositoryInterface::class, $repository);

        $user->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertSame([CategorizedTestNotification::class], $this->sent);
    }

    public function test_an_address_that_is_not_an_account_is_not_filtered(): void
    {
        $user = UserModel::factory()->create(['email' => 'person@example.com']);
        $this->disable($user, 'example.reminders');

        Notification::route('mail', 'person@example.com')->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertSame([CategorizedTestNotification::class], $this->sent);
    }

    public function test_the_preference_never_sends_what_a_module_switched_off(): void
    {
        UserModel::factory()->create()->notify(new CategorizedTestNotification('example.reminders', sendable: false));

        $this->assertSame([], $this->sent);
    }

    public function test_an_anonymized_account_receives_nothing(): void
    {
        $user = UserModel::factory()->create(['anonymized_at' => now()]);

        $user->notify(new CategorizedTestNotification('example.confirmations'));

        $this->assertSame([], $this->sent);
    }

    public function test_a_mail_without_a_known_category_is_sent_with_one_warning_per_class(): void
    {
        $log = Log::spy();
        $user = UserModel::factory()->create();

        $user->notify(new UncategorizedTestNotification);
        $user->notify(new UncategorizedTestNotification);
        $user->notify(new CategorizedTestNotification('example.unknown'));

        $this->assertSame([
            UncategorizedTestNotification::class,
            UncategorizedTestNotification::class,
            CategorizedTestNotification::class,
        ], $this->sent);
        $log->shouldHaveReceived('warning')
            ->withArgs(static fn (string $message): bool => str_contains($message, 'mail category'))
            ->twice();
    }

    public function test_other_channels_are_not_filtered(): void
    {
        $user = UserModel::factory()->create();
        $this->disable($user, 'example.reminders');

        $response = Event::until(new NotificationSending($user, new CategorizedTestNotification('example.reminders'), 'database'));

        $this->assertNull($response);
    }

    public function test_a_mail_it_lets_through_does_not_stop_other_listeners(): void
    {
        $user = UserModel::factory()->create();
        $reached = false;
        Event::listen(NotificationSending::class, function () use (&$reached): void {
            $reached = true;
        });

        $user->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertTrue($reached);
    }

    private function disable(UserModel $user, string $key): void
    {
        $this->app->make(MailPreferenceRepositoryInterface::class)->save(UserId::fromString($user->id), [$key => false]);
    }
}
