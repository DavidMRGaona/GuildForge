<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Subjects;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Domain\Mail\Repositories\MailSubjectMuteRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Tests\Support\Mail\CategorizedTestNotification;
use Tests\Support\Mail\SubjectTestNotification;
use Tests\TestCase;

/**
 * Muting one subject (a game, a tournament...) holds back its optional mail of every category,
 * never its mandatory mail, and nothing about other subjects or other people.
 */
final class MailSubjectMuteDeliveryTest extends TestCase
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
                key: 'example.reminders',
                section: 'Example section',
                label: 'Reminders',
                description: 'Before it starts',
            ),
            MailCategoryDTO::optional(
                key: 'example.changes',
                section: 'Example section',
                label: 'Changes',
                description: 'When something changes',
            ),
        ], 'example');

        Event::listen(NotificationSent::class, function (): void {
            $this->sent++;
        });
    }

    public function test_a_muted_subject_gets_no_optional_mail_of_any_category(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->mute($user, $this->campaign());

        $user->notify(new SubjectTestNotification('example.reminders', $this->campaign()));
        $user->notify(new SubjectTestNotification('example.changes', $this->campaign()));

        $this->assertSame(0, $this->sent);
    }

    public function test_mandatory_mail_about_a_muted_subject_is_always_sent(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->mute($user, $this->campaign());

        $user->notify(new SubjectTestNotification('example.confirmations', $this->campaign()));

        $this->assertSame(1, $this->sent);
    }

    public function test_other_subjects_mail_without_a_subject_and_other_people_are_not_affected(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->service()->mute($user, $this->campaign());

        $user->notify(new SubjectTestNotification('example.reminders', $this->campaign(id: 'another')));
        $user->notify(new SubjectTestNotification('example.reminders', new MailSubjectDTO('example.table', 'c-1', 'Mesa')));
        $user->notify(new SubjectTestNotification('example.reminders', null));
        $user->notify(new CategorizedTestNotification('example.reminders'));
        $other->notify(new SubjectTestNotification('example.reminders', $this->campaign()));

        $this->assertSame(5, $this->sent);
    }

    public function test_unmuting_lets_the_mail_through_again(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->mute($user, $this->campaign());

        $removed = $this->service()->unmute($user, 'example.campaign', 'c-1');
        $user->notify(new SubjectTestNotification('example.reminders', $this->campaign()));

        $this->assertSame('La tumba', $removed?->label);
        $this->assertSame(1, $this->sent);
        $this->assertNull($this->service()->unmute($user, 'example.campaign', 'c-1'));
    }

    public function test_a_subject_that_cannot_be_built_lets_the_mail_through(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->mute($user, $this->campaign());

        $user->notify(new SubjectTestNotification('example.reminders', $this->campaign(), failing: true));

        $this->assertSame(1, $this->sent);
    }

    public function test_mutes_that_cannot_be_read_let_the_mail_through(): void
    {
        $user = UserModel::factory()->create();
        $repository = Mockery::mock(MailSubjectMuteRepositoryInterface::class);
        $repository->shouldReceive('isMuted')->andThrow(new RuntimeException('Database down'));
        $this->app->instance(MailSubjectMuteRepositoryInterface::class, $repository);

        $user->notify(new SubjectTestNotification('example.reminders', $this->campaign()));

        $this->assertSame(1, $this->sent);
    }

    public function test_muting_again_keeps_one_row_and_refreshes_the_stored_names(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->mute($user, $this->campaign());
        $this->service()->mute($user, $this->campaign(label: 'La tumba (2.ª parte)'));

        $muted = $this->service()->mutedFor($user);

        $this->assertCount(1, $muted);
        $this->assertSame('La tumba (2.ª parte)', $muted[0]->label);
        $this->assertSame('example::mail.subjects.campaign', $muted[0]->typeLabel);
    }

    public function test_muted_subjects_are_listed_newest_first(): void
    {
        $user = UserModel::factory()->create();
        $this->travelTo('2026-12-01 10:00:00');
        $this->service()->mute($user, $this->campaign(id: 'first', label: 'Primera'));
        $this->travelTo('2026-12-02 10:00:00');
        $this->service()->mute($user, $this->campaign(id: 'second', label: 'Segunda'));

        $this->assertSame(['Segunda', 'Primera'], array_map(
            static fn ($muted): string => $muted->label,
            $this->service()->mutedFor($user),
        ));
    }

    public function test_forgetting_a_subject_removes_it_for_everybody_and_only_it(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->service()->mute($user, $this->campaign());
        $this->service()->mute($other, $this->campaign());
        $this->service()->mute($other, $this->campaign(id: 'kept'));

        $this->service()->forgetSubject('example.campaign', 'c-1');

        $this->assertSame([], $this->service()->mutedFor($user));
        $this->assertSame(['kept'], array_map(static fn ($muted): string => $muted->id, $this->service()->mutedFor($other)));
    }

    public function test_deleting_the_account_deletes_its_mutes(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->mute($user, $this->campaign());

        $user->forceDelete();

        $this->assertSame(0, DB::table('user_mail_subject_mutes')->count());
    }

    private function campaign(string $id = 'c-1', string $label = 'La tumba'): MailSubjectDTO
    {
        return new MailSubjectDTO('example.campaign', $id, $label, 'example::mail.subjects.campaign');
    }

    private function service(): MailSubjectMuteServiceInterface
    {
        return $this->app->make(MailSubjectMuteServiceInterface::class);
    }
}
