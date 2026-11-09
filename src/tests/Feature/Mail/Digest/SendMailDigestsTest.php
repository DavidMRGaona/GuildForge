<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Digest;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Mail\Enums\MailDelivery;
use App\Domain\Mail\Enums\MailPauseDuration;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

/**
 * The daily digest (core#333): one mail per person, at the hour set in the panel (display zone),
 * with the notices kept before that hour. Running twice sends nothing twice, and a failure
 * keeps the notices for the next run.
 */
final class SendMailDigestsTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    /**
     * @var list<Email>
     */
    private array $sent = [];

    private bool $failing = false;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        // Stored in UTC, shown in Madrid: in November 08:00 there is 07:00 UTC
        $this->useUtcStorage();
        config(['app.display_timezone' => 'Europe/Madrid']);

        $this->app->make(MailCategoryRegistryInterface::class)->registerMany([
            MailCategoryDTO::optional(
                key: 'example.sign-ups',
                section: 'Ejemplo',
                label: 'Inscripciones en tus mesas',
                description: 'Cuando alguien se apunta',
                digestible: true,
            ),
            MailCategoryDTO::optional(
                key: 'example.changes',
                section: 'Ejemplo',
                label: 'Cambios en tus mesas',
                description: 'Cuando algo cambia',
                digestible: true,
            ),
        ], 'example');

        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $this->sent[] = $event->message;
        });
    }

    public function test_at_the_digest_hour_each_person_gets_one_mail_with_their_notices(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->keep($user, 'example.sign-ups', 'Ana se ha apuntado a «La tumba»', '2026-11-09 18:00:00', 'https://example.test/mesas/1');
        $this->keep($user, 'example.changes', 'La *mesa* cambia de hora', '2026-11-10 06:30:00');
        $this->keep($other, 'example.sign-ups', 'Luis se ha apuntado', '2026-11-10 06:59:00');
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(2, $this->sent);
        $mail = $this->mailTo($user);
        $this->assertStringContainsString('2 avisos', (string) $mail->getSubject());
        $html = (string) $mail->getHtmlBody();
        $this->assertStringContainsString('Inscripciones en tus mesas', $html);
        $this->assertStringContainsString('Cambios en tus mesas', $html);
        $this->assertStringContainsString('Ana se ha apuntado a «La tumba»', $html);
        $this->assertStringContainsString('https://example.test/mesas/1', $html);
        // Shown in the display zone, and the text of a module is never Markdown
        $this->assertStringContainsString('09/11 19:00', $html);
        $this->assertStringContainsString('La *mesa* cambia de hora', $html);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_notices_kept_after_the_hour_wait_for_the_next_day(): void
    {
        $user = UserModel::factory()->create();
        $this->keep($user, 'example.sign-ups', 'Antes de la hora', '2026-11-10 06:59:00');
        $this->keep($user, 'example.sign-ups', 'Después de la hora', '2026-11-10 07:01:00');
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(1, $this->sent);
        $this->assertStringNotContainsString('Después de la hora', (string) $this->sent[0]->getHtmlBody());
        $this->assertSame(['Después de la hora'], DB::table('user_mail_digest_items')->pluck('line')->all());
    }

    public function test_before_the_hour_nothing_from_today_is_sent(): void
    {
        $user = UserModel::factory()->create();
        $this->keep($user, 'example.sign-ups', 'De esta madrugada', '2026-11-10 05:00:00');
        $this->travelTo('2026-11-10 06:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(0, $this->sent);
        $this->assertSame(1, DB::table('user_mail_digest_items')->count());
    }

    public function test_the_hour_is_the_one_set_in_the_panel_and_follows_summer_time(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set('mail_digest_hour', '20');
        $user = UserModel::factory()->create();
        // 20:00 in Madrid in July is 18:00 UTC
        $this->keep($user, 'example.sign-ups', 'Antes de las ocho', '2026-07-01 17:59:00');
        $this->keep($user, 'example.sign-ups', 'Después de las ocho', '2026-07-01 18:01:00');
        $this->travelTo('2026-07-01 18:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(1, $this->sent);
        $this->assertSame(['Después de las ocho'], DB::table('user_mail_digest_items')->pluck('line')->all());
    }

    public function test_running_twice_sends_one_mail(): void
    {
        $user = UserModel::factory()->create();
        $this->keep($user, 'example.sign-ups', 'Ana se ha apuntado', '2026-11-09 18:00:00');
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();
        $this->travelTo('2026-11-10 08:05:00');
        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(1, $this->sent);
    }

    public function test_a_failure_while_sending_keeps_the_notices_for_the_next_run(): void
    {
        $user = UserModel::factory()->create();
        $this->keep($user, 'example.sign-ups', 'Ana se ha apuntado', '2026-11-09 18:00:00');
        $this->travelTo('2026-11-10 07:05:00');
        // The mail fails on its way out, after the notices were read
        $this->failing = true;
        Event::listen(NotificationSending::class, function (): void {
            if ($this->failing) {
                throw new RuntimeException('SMTP down');
            }
        });
        Log::spy();

        $this->artisanCommand('mail:send-digests');

        $this->assertCount(0, $this->sent);
        $this->assertSame(1, DB::table('user_mail_digest_items')->count());

        $this->failing = false;
        $this->travelTo('2026-11-10 08:05:00');
        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(1, $this->sentMails());
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_notices_the_person_no_longer_wants_are_dropped_when_the_digest_is_sent(): void
    {
        $user = UserModel::factory()->create();
        $this->keep($user, 'example.sign-ups', 'De una categoría que ya no quiere', '2026-11-09 18:00:00');
        $this->keep($user, 'example.changes', 'De una mesa silenciada', '2026-11-09 18:00:00', subject: new MailSubjectDTO('example.table', 't-1', 'La tumba'));
        $this->keep($user, 'example.changes', 'Sigue queriendo esta', '2026-11-09 19:00:00');
        $this->keep($user, 'example.gone', 'De un módulo desactivado', '2026-11-09 19:00:00');
        $this->app->make(MailPreferenceServiceInterface::class)->update($user, ['example.sign-ups' => MailDelivery::None]);
        $this->app->make(MailSubjectMuteServiceInterface::class)->mute($user, new MailSubjectDTO('example.table', 't-1', 'La tumba'));
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(1, $this->sent);
        $html = (string) $this->sent[0]->getHtmlBody();
        $this->assertStringContainsString('Sigue queriendo esta', $html);
        $this->assertStringNotContainsString('ya no quiere', $html);
        $this->assertStringNotContainsString('silenciada', $html);
        $this->assertStringNotContainsString('desactivado', $html);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_while_paused_the_digest_is_not_sent_and_its_notices_are_dropped(): void
    {
        $user = UserModel::factory()->create();
        $this->keep($user, 'example.sign-ups', 'Ana se ha apuntado', '2026-11-09 18:00:00');
        $this->travelTo('2026-11-10 07:05:00');
        $this->app->make(MailPauseServiceInterface::class)->pause($user, MailPauseDuration::Indefinite);

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(0, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_switching_back_to_immediate_still_sends_what_was_kept(): void
    {
        $user = UserModel::factory()->create();
        $this->keep($user, 'example.sign-ups', 'Ana se ha apuntado', '2026-11-09 18:00:00');
        $this->app->make(MailPreferenceServiceInterface::class)->update($user, ['example.sign-ups' => MailDelivery::Immediate]);
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(1, $this->sent);
    }

    public function test_an_anonymized_account_gets_nothing_and_its_notices_are_dropped(): void
    {
        $user = UserModel::factory()->create(['anonymized_at' => now()]);
        $this->keep($user, 'example.sign-ups', 'Ana se ha apuntado', '2026-11-09 18:00:00');
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertCount(0, $this->sent);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_a_long_digest_lists_the_newest_notices_and_counts_the_rest(): void
    {
        $user = UserModel::factory()->create();
        for ($i = 0; $i < 105; $i++) {
            $this->keep($user, 'example.sign-ups', sprintf('Aviso número %03d', $i), sprintf('2026-11-09 %02d:%02d:00', 8 + intdiv($i, 60), $i % 60));
        }
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $html = (string) $this->sent[0]->getHtmlBody();
        $this->assertStringContainsString('105 avisos', (string) $this->sent[0]->getSubject());
        $this->assertStringContainsString('Aviso número 104', $html);
        $this->assertStringNotContainsString('Aviso número 004', $html);
        $this->assertStringContainsString('Y 5 avisos más', $html);
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
    }

    public function test_notices_older_than_a_week_are_pruned_with_a_warning(): void
    {
        $log = Log::spy();
        $user = UserModel::factory()->create(['anonymized_at' => null]);
        $this->keep($user, 'example.sign-ups', 'Muy antiguo', '2026-11-01 10:00:00');
        $this->travelTo('2026-11-10 06:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
        $this->assertCount(0, $this->sent);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_the_footer_lists_each_category_with_its_unsubscribe_link(): void
    {
        $user = UserModel::factory()->create();
        $links = $this->app->make(MailUnsubscribeLinksInterface::class);
        $this->keep($user, 'example.sign-ups', 'Ana se ha apuntado', '2026-11-09 18:00:00');
        $this->keep($user, 'example.changes', 'La mesa cambia', '2026-11-09 18:00:00');
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $mail = $this->sent[0];
        $html = (string) $mail->getHtmlBody();
        $text = (string) $mail->getTextBody();
        $this->assertStringContainsString('Recibes este resumen', $html);
        foreach (['example.sign-ups', 'example.changes'] as $key) {
            $this->assertStringContainsString(e($links->unsubscribeUrl($user->id, $key)), $html);
            $this->assertStringContainsString($links->unsubscribeUrl($user->id, $key), $text);
        }
        $this->assertStringContainsString(e($links->preferencesUrl()), $html);
        // Several categories: no header could stop them all at once
        $this->assertFalse($mail->getHeaders()->has('List-Unsubscribe'));
    }

    public function test_a_digest_of_one_category_carries_its_one_click_header(): void
    {
        $user = UserModel::factory()->create();
        $links = $this->app->make(MailUnsubscribeLinksInterface::class);
        $this->keep($user, 'example.sign-ups', 'Ana se ha apuntado', '2026-11-09 18:00:00');
        $this->travelTo('2026-11-10 07:05:00');

        $this->artisanCommand('mail:send-digests')->assertSuccessful();

        $headers = $this->sent[0]->getHeaders();
        $this->assertSame('<'.$links->oneClickUrl($user->id, 'example.sign-ups').'>', $headers->get('List-Unsubscribe')?->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $headers->get('List-Unsubscribe-Post')?->getBodyAsString());
    }

    /**
     * Read again after a run (a property narrowed by an earlier assertion would not be).
     *
     * @return list<Email>
     */
    private function sentMails(): array
    {
        return $this->sent;
    }

    private function keep(UserModel $user, string $category, string $line, string $at, ?string $url = null, ?MailSubjectDTO $subject = null): void
    {
        DB::table('user_mail_digest_items')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'category' => $category,
            'line' => $line,
            'url' => $url,
            'subject_type' => $subject?->type,
            'subject_id' => $subject?->id,
            'created_at' => $at,
        ]);
    }

    private function mailTo(UserModel $user): Email
    {
        foreach ($this->sent as $mail) {
            if ($mail->getTo()[0]->getAddress() === $user->email) {
                return $mail;
            }
        }

        $this->fail('No digest was sent to '.$user->email);
    }
}
