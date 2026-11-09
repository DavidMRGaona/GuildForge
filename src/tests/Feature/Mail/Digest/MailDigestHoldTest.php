<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Digest;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailDigestLineDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Domain\Mail\Enums\MailDelivery;
use App\Domain\Mail\Enums\MailPauseDuration;
use App\Domain\Mail\Repositories\MailDigestItemRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Support\Mail\CategorizedTestNotification;
use Tests\Support\Mail\DigestLineTestNotification;
use Tests\TestCase;

/**
 * Choosing «resumen diario» for a digestible category keeps its optional notices for the daily
 * digest instead of sending them (core#333). Everything else about delivery stays as it was.
 */
final class MailDigestHoldTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $sent = 0;

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
                key: 'example.sign-ups',
                section: 'Example section',
                label: 'Sign-ups to your tables',
                description: 'When someone joins',
                digestible: true,
            ),
            MailCategoryDTO::optional(
                key: 'example.reminders',
                section: 'Example section',
                label: 'Reminders',
                description: 'Before it starts',
            ),
        ], 'example');

        Event::listen(NotificationSent::class, function (): void {
            $this->sent++;
        });
    }

    public function test_a_digest_choice_keeps_the_notice_instead_of_sending_it(): void
    {
        $user = UserModel::factory()->create();
        $this->choose($user, 'example.sign-ups', MailDelivery::Digest);

        $user->notify(new DigestLineTestNotification(
            'example.sign-ups',
            new MailDigestLineDTO('Ana se ha apuntado a «La tumba»', 'https://example.test/mesas/1'),
            new MailSubjectDTO('example.table', 't-1', 'La tumba'),
        ));

        $this->assertSame(0, $this->sent);
        $item = DB::table('user_mail_digest_items')->sole();
        $this->assertSame($user->id, $item->user_id);
        $this->assertSame('example.sign-ups', $item->category);
        $this->assertSame('Ana se ha apuntado a «La tumba»', $item->line);
        $this->assertSame('https://example.test/mesas/1', $item->url);
        $this->assertSame('example.table', $item->subject_type);
        $this->assertSame('t-1', $item->subject_id);
    }

    public function test_by_default_the_notice_is_sent_at_once(): void
    {
        $user = UserModel::factory()->create();

        $user->notify(new DigestLineTestNotification('example.sign-ups', new MailDigestLineDTO('Ana se ha apuntado')));

        $this->assertSame(1, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_choosing_not_to_receive_keeps_nothing_and_sends_nothing(): void
    {
        $user = UserModel::factory()->create();
        $this->choose($user, 'example.sign-ups', MailDelivery::None);

        $user->notify(new DigestLineTestNotification('example.sign-ups', new MailDigestLineDTO('Ana se ha apuntado')));

        $this->assertSame(0, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_a_mandatory_category_is_never_kept_whatever_is_stored(): void
    {
        $user = UserModel::factory()->create();
        $this->storeRow($user, 'example.confirmations', enabled: true, digest: true);

        $user->notify(new CategorizedTestNotification('example.confirmations'));

        $this->assertSame(1, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_a_category_that_is_not_digestible_any_more_is_sent_at_once(): void
    {
        $user = UserModel::factory()->create();
        // As left by a module that stopped marking the category as digestible
        $this->storeRow($user, 'example.reminders', enabled: true, digest: true);

        $user->notify(new CategorizedTestNotification('example.reminders'));

        $this->assertSame(1, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_while_paused_the_notice_is_dropped_not_kept(): void
    {
        $user = UserModel::factory()->create();
        $this->choose($user, 'example.sign-ups', MailDelivery::Digest);
        $this->app->make(MailPauseServiceInterface::class)->pause($user, MailPauseDuration::Indefinite);

        $user->notify(new DigestLineTestNotification('example.sign-ups', new MailDigestLineDTO('Ana se ha apuntado')));

        $this->assertSame(0, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_a_notice_about_a_muted_subject_is_dropped_not_kept(): void
    {
        $user = UserModel::factory()->create();
        $this->choose($user, 'example.sign-ups', MailDelivery::Digest);
        $subject = new MailSubjectDTO('example.table', 't-1', 'La tumba');
        $this->app->make(MailSubjectMuteServiceInterface::class)->mute($user, $subject);

        $user->notify(new DigestLineTestNotification('example.sign-ups', new MailDigestLineDTO('Ana se ha apuntado'), $subject));

        $this->assertSame(0, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_without_a_line_of_its_own_the_mail_subject_and_action_are_kept(): void
    {
        $user = UserModel::factory()->create();
        $this->choose($user, 'example.sign-ups', MailDelivery::Digest);

        $user->notify(new class extends Notification implements HasMailCategory
        {
            /**
             * @return list<string>
             */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)
                    ->subject("Nueva inscripción\n en «La tumba»")
                    ->line('Ana se ha apuntado.')
                    ->action('Ver la mesa', 'https://example.test/mesas/1');
            }

            public function mailCategory(): string
            {
                return 'example.sign-ups';
            }
        });

        $item = DB::table('user_mail_digest_items')->sole();
        $this->assertSame('Nueva inscripción en «La tumba»', $item->line);
        $this->assertSame('https://example.test/mesas/1', $item->url);
    }

    public function test_a_line_that_returns_null_or_throws_falls_back_to_the_mail_subject(): void
    {
        $user = UserModel::factory()->create();
        $this->choose($user, 'example.sign-ups', MailDelivery::Digest);

        $user->notify(new DigestLineTestNotification('example.sign-ups', null));
        $user->notify(new DigestLineTestNotification('example.sign-ups', new MailDigestLineDTO('Unused'), failing: true));

        $this->assertSame(0, $this->sent);
        $this->assertSame(['Test subject', 'Test subject'], DB::table('user_mail_digest_items')->pluck('line')->all());
        $this->assertSame(['https://example.test/fallback', 'https://example.test/fallback'], DB::table('user_mail_digest_items')->pluck('url')->all());
    }

    public function test_a_notification_whose_mail_is_not_a_mail_message_is_sent_at_once(): void
    {
        $user = UserModel::factory()->create();
        $this->choose($user, 'example.sign-ups', MailDelivery::Digest);

        $user->notify(new class extends Notification implements HasMailCategory
        {
            /**
             * @return list<string>
             */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): Mailable
            {
                /** @var UserModel $notifiable */
                return (new Mailable)->to($notifiable->email)->subject('Custom')->html('<p>Custom</p>');
            }

            public function mailCategory(): string
            {
                return 'example.sign-ups';
            }
        });

        $this->assertSame(1, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_a_notice_that_cannot_be_kept_is_sent_at_once(): void
    {
        $log = Log::spy();
        $user = UserModel::factory()->create();
        $this->choose($user, 'example.sign-ups', MailDelivery::Digest);
        $items = Mockery::mock(MailDigestItemRepositoryInterface::class);
        $items->shouldReceive('add')->andThrow(new RuntimeException('Database down'));
        $this->app->instance(MailDigestItemRepositoryInterface::class, $items);

        $user->notify(new DigestLineTestNotification('example.sign-ups', new MailDigestLineDTO('Ana se ha apuntado')));

        $this->assertSame(1, $this->sent);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_guest_address_is_never_kept(): void
    {
        NotificationFacade::route('mail', 'guest@example.test')
            ->notify(new DigestLineTestNotification('example.sign-ups', new MailDigestLineDTO('Ana se ha apuntado')));

        $this->assertSame(1, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    private function choose(UserModel $user, string $key, MailDelivery $delivery): void
    {
        $this->app->make(MailPreferenceServiceInterface::class)->update($user, [$key => $delivery]);
    }

    private function storeRow(UserModel $user, string $key, bool $enabled, bool $digest): void
    {
        DB::table('user_mail_preferences')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'category' => $key,
            'enabled' => $enabled,
            'digest' => $digest,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
