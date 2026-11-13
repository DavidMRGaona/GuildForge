<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Proposals\DTOs\EventProposalDataDTO;
use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\ProposalResolvedNotification;
use App\Notifications\ProposalToReviewNotification;
use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Mail and bell notices of proposals from the web (core#577).
 */
final class ProposalNoticesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(enabled: true),
            articles: new ProposalTypeSettingsDTO(enabled: true),
            maxPending: 10,
            maxOpenDrafts: 10,
            submissionsPerHour: 20,
        ));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'role-'.uniqid(), 'display_name' => 'Rol']);
        foreach ($permissions as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $role->permissions()->attach(PermissionModel::query()->firstOrCreate(['key' => $key], ['label' => $key, 'resource' => $resource, 'action' => $action])->id);
        }

        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function service(): ProposalServiceInterface
    {
        return app(ProposalServiceInterface::class);
    }

    private function draft(UserModel $author, string $title = 'Jornada de rol'): string
    {
        return $this->service()->createEvent($author->id, new EventProposalDataDTO(
            title: $title,
            description: '<p>Ven</p>',
            startDate: new DateTimeImmutable('2030-11-20 17:00:00 UTC'),
            endDate: new DateTimeImmutable('2030-11-20 21:00:00 UTC'),
            tagIds: [],
        ));
    }

    private function pending(UserModel $author, string $title = 'Jornada de rol'): string
    {
        $id = $this->draft($author, $title);
        $this->service()->submit($author->id, ProposalType::Event, $id);

        return $id;
    }

    public function test_reviewers_of_the_type_are_notified_and_nobody_else(): void
    {
        Notification::fake();
        $author = UserModel::factory()->create();
        $reviewer = $this->userWith(['events.review']);
        $articleReviewer = $this->userWith(['articles.review']);
        $plain = UserModel::factory()->create();

        $this->pending($author);

        Notification::assertSentTo($reviewer, ProposalToReviewNotification::class);
        Notification::assertNotSentTo([$articleReviewer, $plain, $author], ProposalToReviewNotification::class);
    }

    public function test_an_author_who_can_review_is_not_told_about_their_own_submission(): void
    {
        Notification::fake();
        $author = $this->userWith(['events.review']);

        $this->pending($author);

        Notification::assertNotSentTo($author, ProposalToReviewNotification::class);
    }

    public function test_publishing_directly_notifies_neither_reviewers_nor_the_author(): void
    {
        Notification::fake();
        $author = $this->userWith(['events.publish_proposals_directly']);
        $this->userWith(['events.review']);

        $this->pending($author);

        Notification::assertNothingSent();
    }

    public function test_the_author_is_told_when_it_is_approved_returned_or_rejected_with_the_reason(): void
    {
        Notification::fake();
        $author = UserModel::factory()->create();
        $reviewer = $this->userWith(['events.review']);

        $approved = $this->pending($author, 'Aprobada');
        $this->service()->approve($reviewer->id, ProposalType::Event, $approved);
        $returned = $this->pending($author, 'Devuelta');
        $this->service()->returnToDraft($reviewer->id, ProposalType::Event, $returned, 'Falta el lugar');
        $rejected = $this->pending($author, 'Rechazada');
        $this->service()->reject($reviewer->id, ProposalType::Event, $rejected, 'No encaja');

        $sent = Notification::sent($author, ProposalResolvedNotification::class);
        $this->assertCount(3, $sent);
        $bodies = $sent->map(fn (ProposalResolvedNotification $n): string => implode(' ', array_map(
            fn ($line): string => (string) $line,
            $n->toMail($author)->introLines,
        )))->implode(' | ');
        $this->assertStringContainsString('Falta el lugar', $bodies);
        $this->assertStringContainsString('No encaja', $bodies);
        $this->assertStringContainsString('aprobado', $bodies);
    }

    public function test_the_reviewer_who_is_also_the_author_is_not_notified_of_their_own_review(): void
    {
        Notification::fake();
        $both = $this->userWith(['events.review']);
        $id = $this->draft($both);
        $this->service()->submit($both->id, ProposalType::Event, $id);

        $this->service()->reject($both->id, ProposalType::Event, $id, 'Nope');

        Notification::assertNotSentTo($both, ProposalResolvedNotification::class);
    }

    public function test_texts_written_by_people_are_escaped_in_the_mail(): void
    {
        Notification::fake();
        $author = UserModel::factory()->create(['display_name' => 'Ana [x](http://evil.test)']);
        $reviewer = $this->userWith(['events.review']);
        $id = $this->pending($author, 'Torneo *negrita* [enlace](http://evil.test)');

        $mail = Notification::sent($reviewer, ProposalToReviewNotification::class)->first()->toMail($reviewer);
        $line = (string) $mail->introLines[0];
        $this->assertStringContainsString('\\[enlace\\]\\(http://evil\\.test\\)', $line);
        $this->assertStringContainsString('\\*negrita\\*', $line);
        $this->assertStringContainsString('Ana \\[x\\]\\(http://evil\\.test\\)', $line);

        $this->service()->reject($reviewer->id, ProposalType::Event, $id, 'Mira [aquí](http://evil.test)');
        $resolved = Notification::sent($author, ProposalResolvedNotification::class)->first()->toMail($author);
        $this->assertStringContainsString('Mira \\[aquí\\]\\(http://evil\\.test\\)', (string) $resolved->introLines[1]);
    }

    public function test_the_bell_links_reviewers_to_the_panel_and_the_author_to_their_profile(): void
    {
        $author = UserModel::factory()->create();
        $reviewer = $this->userWith(['events.review']);
        $id = $this->pending($author);
        $notices = app(SiteNotificationServiceInterface::class);

        $forReviewer = $notices->latest($reviewer, 10);
        $this->assertCount(1, $forReviewer);
        $this->assertSame(EventResource::getUrl('edit', ['record' => $id], isAbsolute: false), $forReviewer[0]->url);

        $this->service()->approve($reviewer->id, ProposalType::Event, $id);
        $forAuthor = $notices->latest($author, 10);
        $this->assertCount(1, $forAuthor);
        $this->assertSame(route('profile.show', ['tab' => 'proposals'], absolute: false), $forAuthor[0]->url);
    }

    public function test_a_review_notice_is_dropped_when_the_proposal_was_already_reviewed(): void
    {
        $author = UserModel::factory()->create();
        $reviewer = $this->userWith(['events.review']);
        $id = $this->pending($author);
        EventModel::query()->includingUnsubmittedProposals()->whereKey($id)->update(['proposal_status' => ProposalStatus::Rejected->value]);

        $notification = new ProposalToReviewNotification(ProposalType::Event, $id, 'X', 'Y', $author->id);

        $this->assertFalse($notification->shouldSend($reviewer, 'mail'));
        $this->assertFalse($notification->shouldSend($author, 'mail'));
    }

    public function test_both_categories_are_registered_with_the_bell_and_the_review_one_is_digestible(): void
    {
        $registry = app(MailCategoryRegistryInterface::class);

        $review = $registry->find(CoreMailCategories::PROPOSALS_REVIEW);
        $status = $registry->find(CoreMailCategories::PROPOSALS_STATUS);

        $this->assertNotNull($review);
        $this->assertNotNull($status);
        $this->assertTrue($review->digestible);
        $this->assertTrue($review->site);
        $this->assertTrue($status->site);
        $this->assertFalse($status->digestible);
        $audience = $review->audience;
        $this->assertInstanceOf(Closure::class, $audience);
        $this->assertTrue($audience(UserModel::factory()->admin()->create()));
        $this->assertFalse($audience(UserModel::factory()->create()));
    }

    public function test_the_status_category_shows_to_whoever_may_propose_or_has_proposals(): void
    {
        $status = app(MailCategoryRegistryInterface::class)->find(CoreMailCategories::PROPOSALS_STATUS);
        $this->assertNotNull($status);
        $audience = $status->audience;
        $this->assertInstanceOf(Closure::class, $audience);
        $author = UserModel::factory()->create();
        $this->draft($author);

        $this->assertTrue($audience($author));
    }

    public function test_deleting_a_proposal_forgets_its_notices_and_mutes(): void
    {
        $author = UserModel::factory()->create();
        $reviewer = $this->userWith(['events.review']);
        $id = $this->pending($author);
        $notices = app(SiteNotificationServiceInterface::class);
        $this->assertSame(1, $notices->unreadCount($reviewer));
        $other = $this->pending($author, 'Otra');

        $this->service()->withdraw($author->id, ProposalType::Event, $id);
        $this->service()->delete($author->id, ProposalType::Event, $id);

        $this->assertSame(1, $notices->unreadCount($reviewer));
        $this->assertCount(1, $notices->latest($reviewer, 10));
        $this->assertSame(EventResource::getUrl('edit', ['record' => $other], isAbsolute: false), $notices->latest($reviewer, 10)[0]->url);
    }

    public function test_purging_and_removing_an_account_forget_the_notices_too(): void
    {
        $author = UserModel::factory()->create();
        $reviewer = $this->userWith(['events.review']);
        $this->pending($author);
        $notices = app(SiteNotificationServiceInterface::class);
        $this->assertSame(1, $notices->unreadCount($reviewer));

        $this->service()->deleteUnpublishedOf($author->id);

        $this->assertSame(0, $notices->unreadCount($reviewer));
    }

    public function test_forgetting_is_called_for_the_proposal_subject_only(): void
    {
        $spy = $this->createMock(MailSubjectMuteServiceInterface::class);
        $author = UserModel::factory()->create();
        $draft = EventModel::factory()->proposal(ProposalStatus::Draft, $author)->create();
        $plain = EventModel::factory()->create();
        $spy->expects($this->once())->method('forgetSubject')->with('core.proposal', $draft->id);
        $this->app->instance(MailSubjectMuteServiceInterface::class, $spy);

        $plain->delete();
        $draft->delete();
    }
}
