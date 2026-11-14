<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Proposals;

use App\Application\Proposals\Contracts\ProposalApprovalStepInterface;
use App\Application\Proposals\DTOs\ArticleProposalDataDTO;
use App\Application\Proposals\DTOs\EventProposalDataDTO;
use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Events\ProposalApproved;
use App\Domain\Proposals\Events\ProposalRejected;
use App\Domain\Proposals\Events\ProposalReturned;
use App\Domain\Proposals\Events\ProposalSubmitted;
use App\Domain\Proposals\Events\ProposalWithdrawn;
use App\Domain\Proposals\Exceptions\InvalidProposalDataException;
use App\Domain\Proposals\Exceptions\InvalidProposalTransitionException;
use App\Domain\Proposals\Exceptions\ProposalException;
use App\Domain\Proposals\Exceptions\ProposalLimitReachedException;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Domain\Proposals\Exceptions\ProposalNotFoundException;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

final class ProposalServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array DOMAIN_EVENTS = [
        ProposalSubmitted::class,
        ProposalApproved::class,
        ProposalReturned::class,
        ProposalRejected::class,
        ProposalWithdrawn::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));
        $this->configure();
    }

    private function configure(int $maxPending = 3, int $maxDrafts = 5, int $perHour = 5, bool $events = true, bool $articles = true): void
    {
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(enabled: $events),
            articles: new ProposalTypeSettingsDTO(enabled: $articles),
            maxPending: $maxPending,
            maxOpenDrafts: $maxDrafts,
            submissionsPerHour: $perHour,
        ));
    }

    private function service(): ProposalServiceInterface
    {
        return app(ProposalServiceInterface::class);
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

    /**
     * @param  list<string>  $tags
     */
    private function eventData(string $title = 'Jornada de rol', array $tags = []): EventProposalDataDTO
    {
        return new EventProposalDataDTO(
            title: $title,
            description: '<p>Ven a jugar</p><script>alert(1)</script>',
            startDate: new DateTimeImmutable('2026-11-20 17:00:00 UTC'),
            endDate: new DateTimeImmutable('2026-11-20 21:00:00 UTC'),
            location: 'Local',
            imagePublicId: 'events/2026/11/cartel.jpg',
            tagIds: $tags,
            note: 'Necesito mesas',
        );
    }

    private function articleData(string $title = 'Crónica del torneo'): ArticleProposalDataDTO
    {
        return new ArticleProposalDataDTO(
            title: $title,
            content: '<p>Fue genial</p><img src="x" onerror="alert(1)">',
            excerpt: 'Resumen',
            featuredImagePublicId: 'articles/2026/11/foto.jpg',
        );
    }

    private function event(string $id): EventModel
    {
        return EventModel::query()->includingUnsubmittedProposals()->findOrFail($id);
    }

    private function article(string $id): ArticleModel
    {
        return ArticleModel::query()->includingUnsubmittedProposals()->findOrFail($id);
    }

    /**
     * @param  class-string<ProposalException>  $exception
     */
    private function assertRefused(string $exception, string $reason, Closure $action): void
    {
        try {
            $action();
            $this->fail('Expected '.$exception.' ('.$reason.')');
        } catch (ProposalException $e) {
            $this->assertInstanceOf($exception, $e);
            $this->assertSame($reason, $e->reason);
        }
    }

    // Creating and editing

    public function test_creating_an_event_proposal_stores_an_unsubmitted_draft(): void
    {
        $user = UserModel::factory()->create();
        // Additional tags only: whoever proposes never picks the category (core#654)
        $category = TagModel::factory()->create(['applies_to' => ['events', 'articles']]);
        $tag = TagModel::factory()->withParent($category)->create(['applies_to' => ['events']]);
        $articleTag = TagModel::factory()->withParent($category)->create(['applies_to' => ['articles']]);

        $id = $this->service()->createEvent($user->id, $this->eventData(tags: [$tag->id, $articleTag->id, $category->id, 'not-a-uuid']));

        $event = $this->event($id);
        $this->assertSame(ProposalStatus::Draft, $event->proposal_status);
        $this->assertSame($user->id, $event->proposed_by);
        $this->assertNull($event->submitted_at);
        $this->assertFalse($event->is_published);
        $this->assertSame('Jornada de rol', $event->title);
        $this->assertStringStartsWith('jornada-de-rol-', $event->slug);
        $this->assertStringNotContainsString('<script', $event->description);
        $this->assertSame('Necesito mesas', $event->proposal_note);
        $this->assertNull($event->member_price);
        $this->assertSame([$tag->id], $event->tags()->pluck('tags.id')->all());
        $this->assertSame('2026-11-20 17:00:00', $event->start_date->utc()->format('Y-m-d H:i:s'));
        $this->assertNull(EventModel::query()->find($id), 'hidden until submitted');
    }

    public function test_creating_an_article_proposal_makes_the_proposer_its_author(): void
    {
        $user = UserModel::factory()->create();

        $id = $this->service()->createArticle($user->id, $this->articleData());

        $article = $this->article($id);
        $this->assertSame(ProposalStatus::Draft, $article->proposal_status);
        $this->assertSame($user->id, $article->proposed_by);
        $this->assertSame($user->id, $article->author_id);
        $this->assertSame('cronica-del-torneo', $article->slug);
        $this->assertStringNotContainsString('onerror', $article->content);
        $this->assertNull($article->published_at);
    }

    public function test_article_slugs_stay_unique_even_against_hidden_drafts(): void
    {
        $user = UserModel::factory()->create();

        $first = $this->service()->createArticle($user->id, $this->articleData('Crónica'));
        $second = $this->service()->createArticle($user->id, $this->articleData('Crónica'));

        $this->assertSame('cronica', $this->article($first)->slug);
        $this->assertSame('cronica-2', $this->article($second)->slug);
    }

    public function test_invalid_data_is_refused(): void
    {
        $user = UserModel::factory()->create();

        $this->assertRefused(InvalidProposalDataException::class, 'invalid_title', fn () => $this->service()->createEvent($user->id, $this->eventData('   ')));
        $this->assertRefused(InvalidProposalDataException::class, 'invalid_dates', fn () => $this->service()->createEvent($user->id, new EventProposalDataDTO(
            title: 'Al revés',
            description: '',
            startDate: new DateTimeImmutable('2026-11-20 21:00:00 UTC'),
            endDate: new DateTimeImmutable('2026-11-20 17:00:00 UTC'),
        )));
        $this->assertRefused(InvalidProposalDataException::class, 'invalid_image', fn () => $this->service()->createEvent($user->id, new EventProposalDataDTO(
            title: 'Imagen ajena',
            description: '',
            startDate: new DateTimeImmutable('2026-11-20 17:00:00 UTC'),
            endDate: new DateTimeImmutable('2026-11-20 21:00:00 UTC'),
            imagePublicId: 'avatars/otra.jpg',
        )));
        $this->assertSame(0, EventModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_only_eligible_people_may_create(): void
    {
        $this->configure(events: false);

        $this->assertRefused(ProposalNotAllowedException::class, 'disabled', fn () => $this->service()->createEvent(UserModel::factory()->create()->id, $this->eventData()));
        $this->assertRefused(ProposalNotAllowedException::class, 'email_unverified', fn () => $this->service()->createArticle(UserModel::factory()->unverified()->create()->id, $this->articleData()));
    }

    public function test_the_open_drafts_cap_counts_both_types(): void
    {
        $this->configure(maxDrafts: 2);
        $user = UserModel::factory()->create();
        $this->service()->createEvent($user->id, $this->eventData());
        $this->service()->createArticle($user->id, $this->articleData());

        $this->assertRefused(ProposalLimitReachedException::class, ProposalLimitReachedException::DRAFTS, fn () => $this->service()->createEvent($user->id, $this->eventData()));
        // Someone else is not affected
        $this->service()->createEvent(UserModel::factory()->create()->id, $this->eventData());
    }

    public function test_the_author_edits_a_draft_only(): void
    {
        $user = UserModel::factory()->create();
        $id = $this->service()->createEvent($user->id, $this->eventData());

        $this->service()->updateEvent($user->id, $id, $this->eventData('Jornada de estrategia'));
        $this->assertSame('Jornada de estrategia', $this->event($id)->title);

        $this->assertRefused(ProposalNotAllowedException::class, 'not_author', fn () => $this->service()->updateEvent(UserModel::factory()->create()->id, $id, $this->eventData()));

        $this->service()->submit($user->id, ProposalType::Event, $id);
        $this->assertRefused(InvalidProposalTransitionException::class, 'invalid_status', fn () => $this->service()->updateEvent($user->id, $id, $this->eventData()));
    }

    public function test_content_created_in_the_panel_is_not_a_proposal(): void
    {
        $user = UserModel::factory()->create();
        $panel = EventModel::factory()->create();

        $this->assertRefused(ProposalNotFoundException::class, 'not_found', fn () => $this->service()->updateEvent($user->id, $panel->id, $this->eventData()));
        $this->assertRefused(ProposalNotFoundException::class, 'not_found', fn () => $this->service()->delete($user->id, ProposalType::Event, $panel->id));
        $this->assertRefused(ProposalNotFoundException::class, 'not_found', fn () => $this->service()->submit($user->id, ProposalType::Article, 'not-a-uuid'));
    }

    public function test_an_author_who_lost_access_may_not_edit_nor_submit_but_may_delete(): void
    {
        $user = UserModel::factory()->create();
        $id = $this->service()->createArticle($user->id, $this->articleData());
        $this->configure(articles: false);

        $this->assertRefused(ProposalNotAllowedException::class, 'disabled', fn () => $this->service()->updateArticle($user->id, $id, $this->articleData()));
        $this->assertRefused(ProposalNotAllowedException::class, 'disabled', fn () => $this->service()->submit($user->id, ProposalType::Article, $id));

        $this->service()->delete($user->id, ProposalType::Article, $id);
        $this->assertSame(0, ArticleModel::query()->includingUnsubmittedProposals()->count());
    }

    // Submitting

    public function test_submitting_sends_the_proposal_to_review(): void
    {
        Event::fake(self::DOMAIN_EVENTS);
        $user = UserModel::factory()->create();
        $id = $this->service()->createEvent($user->id, $this->eventData());

        $status = $this->service()->submit($user->id, ProposalType::Event, $id);

        $this->assertSame(ProposalStatus::PendingReview, $status);
        $event = $this->event($id);
        $this->assertSame(ProposalStatus::PendingReview, $event->proposal_status);
        $this->assertNotNull($event->submitted_at);
        $this->assertFalse($event->is_published);
        $this->assertNotNull(EventModel::query()->find($id), 'visible to the panel once submitted');
        Event::assertDispatched(ProposalSubmitted::class, fn (ProposalSubmitted $e): bool => $e->type === ProposalType::Event && $e->id === $id && $e->proposerId === $user->id);
        Event::assertNotDispatched(ProposalApproved::class);
    }

    public function test_the_pending_cap_counts_both_types(): void
    {
        $this->configure(maxPending: 1);
        $user = UserModel::factory()->create();
        $event = $this->service()->createEvent($user->id, $this->eventData());
        $article = $this->service()->createArticle($user->id, $this->articleData());
        $this->service()->submit($user->id, ProposalType::Event, $event);

        $this->assertRefused(ProposalLimitReachedException::class, ProposalLimitReachedException::PENDING, fn () => $this->service()->submit($user->id, ProposalType::Article, $article));
        $this->assertSame(ProposalStatus::Draft, $this->article($article)->proposal_status);
    }

    public function test_submissions_per_hour_are_capped(): void
    {
        $this->configure(maxPending: 10, perHour: 2);
        $user = UserModel::factory()->create();
        $ids = [];
        foreach (range(1, 3) as $i) {
            $ids[] = $this->service()->createEvent($user->id, $this->eventData('Jornada '.$i));
        }

        $this->service()->submit($user->id, ProposalType::Event, $ids[0]);
        $this->service()->withdraw($user->id, ProposalType::Event, $ids[0]);
        $this->service()->submit($user->id, ProposalType::Event, $ids[1]);

        $this->assertRefused(ProposalLimitReachedException::class, ProposalLimitReachedException::HOURLY, fn () => $this->service()->submit($user->id, ProposalType::Event, $ids[2]));

        $this->travel(61)->minutes();
        $this->assertSame(ProposalStatus::PendingReview, $this->service()->submit($user->id, ProposalType::Event, $ids[2]));
    }

    public function test_submitting_with_the_publish_directly_permission_publishes_at_once(): void
    {
        Event::fake(self::DOMAIN_EVENTS);
        $user = $this->userWith(['articles.publish_proposals_directly']);
        $id = $this->service()->createArticle($user->id, $this->articleData());

        $status = $this->service()->submit($user->id, ProposalType::Article, $id);

        $this->assertSame(ProposalStatus::Approved, $status);
        $article = $this->article($id);
        $this->assertSame(ProposalStatus::Approved, $article->proposal_status);
        $this->assertTrue($article->is_published);
        $this->assertNotNull($article->published_at);
        $this->assertNotNull($article->reviewed_at);
        Event::assertDispatched(ProposalApproved::class, fn (ProposalApproved $e): bool => $e->automatic && $e->id === $id && $e->reviewerId === null);
        Event::assertNotDispatched(ProposalSubmitted::class);
    }

    public function test_the_publish_directly_permission_of_one_type_does_not_cover_the_other(): void
    {
        $user = $this->userWith(['articles.publish_proposals_directly']);
        $id = $this->service()->createEvent($user->id, $this->eventData());

        $this->assertSame(ProposalStatus::PendingReview, $this->service()->submit($user->id, ProposalType::Event, $id));
    }

    public function test_a_failed_direct_publication_leaves_the_proposal_in_review(): void
    {
        Event::fake(self::DOMAIN_EVENTS);
        $this->failingApprovalStep();
        $user = $this->userWith(['events.publish_proposals_directly']);
        $id = $this->service()->createEvent($user->id, $this->eventData());

        $this->assertSame(ProposalStatus::PendingReview, $this->service()->submit($user->id, ProposalType::Event, $id));
        $this->assertFalse($this->event($id)->is_published);
        Event::assertDispatched(ProposalSubmitted::class);
        Event::assertNotDispatched(ProposalApproved::class);
    }

    // Withdrawing and deleting

    public function test_the_author_withdraws_a_proposal_back_to_an_unsubmitted_draft(): void
    {
        Event::fake(self::DOMAIN_EVENTS);
        $user = UserModel::factory()->create();
        $id = $this->service()->createEvent($user->id, $this->eventData());
        $this->service()->submit($user->id, ProposalType::Event, $id);

        $this->service()->withdraw($user->id, ProposalType::Event, $id);

        $event = $this->event($id);
        $this->assertSame(ProposalStatus::Draft, $event->proposal_status);
        $this->assertNull($event->submitted_at);
        Event::assertDispatched(ProposalWithdrawn::class, fn (ProposalWithdrawn $e): bool => $e->id === $id && $e->proposerId === $user->id);
        $this->assertRefused(InvalidProposalTransitionException::class, 'invalid_status', fn () => $this->service()->withdraw($user->id, ProposalType::Event, $id));
    }

    public function test_an_author_may_delete_drafts_and_rejected_proposals_only(): void
    {
        $user = UserModel::factory()->create();
        $pending = ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $user)->create();
        $approved = ArticleModel::factory()->proposal(ProposalStatus::Approved, $user)->create();
        $rejected = ArticleModel::factory()->proposal(ProposalStatus::Rejected, $user)->create();

        $this->assertRefused(InvalidProposalTransitionException::class, 'invalid_status', fn () => $this->service()->delete($user->id, ProposalType::Article, $pending->id));
        $this->assertRefused(InvalidProposalTransitionException::class, 'invalid_status', fn () => $this->service()->delete($user->id, ProposalType::Article, $approved->id));
        $this->assertRefused(ProposalNotAllowedException::class, 'not_author', fn () => $this->service()->delete(UserModel::factory()->create()->id, ProposalType::Article, $rejected->id));

        $this->service()->delete($user->id, ProposalType::Article, $rejected->id);
        $this->assertNull(ArticleModel::query()->find($rejected->id));
    }

    // Reviewing

    public function test_approving_publishes_and_records_who_and_when(): void
    {
        Event::fake(self::DOMAIN_EVENTS);
        $author = UserModel::factory()->create();
        $reviewer = $this->userWith(['events.review']);
        $id = $this->service()->createEvent($author->id, $this->eventData());
        $this->service()->submit($author->id, ProposalType::Event, $id);

        $this->service()->approve($reviewer->id, ProposalType::Event, $id);

        $event = $this->event($id);
        $this->assertSame(ProposalStatus::Approved, $event->proposal_status);
        $this->assertTrue($event->is_published);
        $this->assertSame($reviewer->id, $event->reviewed_by);
        $this->assertSame('2026-11-15 12:00:00', $event->reviewed_at?->utc()->format('Y-m-d H:i:s'));
        $this->get('/eventos/'.$event->slug)->assertOk();
        Event::assertDispatched(ProposalApproved::class, fn (ProposalApproved $e): bool => ! $e->automatic && $e->reviewerId === $reviewer->id && $e->proposerId === $author->id);
    }

    public function test_approving_an_article_sets_its_publication_date(): void
    {
        $author = UserModel::factory()->create();
        $pending = ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $author)->create();

        $this->service()->approve($this->userWith(['articles.review'])->id, ProposalType::Article, $pending->id);

        $article = $this->article($pending->id);
        $this->assertTrue($article->is_published);
        $this->assertSame('2026-11-15 12:00:00', $article->published_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($author->id, $article->author_id);
    }

    public function test_reviewing_needs_the_types_review_permission(): void
    {
        $pending = EventModel::factory()->proposal(ProposalStatus::PendingReview)->create();
        $articleReviewer = $this->userWith(['articles.review', 'events.update']);

        $this->assertRefused(ProposalNotAllowedException::class, 'review_denied', fn () => $this->service()->approve($articleReviewer->id, ProposalType::Event, $pending->id));
        $this->assertRefused(ProposalNotAllowedException::class, 'review_denied', fn () => $this->service()->reject($articleReviewer->id, ProposalType::Event, $pending->id, 'No'));
        $this->assertFalse($this->event($pending->id)->is_published);
    }

    public function test_only_proposals_in_review_are_reviewed(): void
    {
        $reviewer = $this->userWith(['events.review']);
        $draft = EventModel::factory()->proposal(ProposalStatus::Draft, submitted: true)->create();
        $rejected = EventModel::factory()->proposal(ProposalStatus::Rejected)->create();

        $this->assertRefused(InvalidProposalTransitionException::class, 'invalid_status', fn () => $this->service()->approve($reviewer->id, ProposalType::Event, $draft->id));
        $this->assertRefused(InvalidProposalTransitionException::class, 'invalid_status', fn () => $this->service()->approve($reviewer->id, ProposalType::Event, $rejected->id));
        $this->assertRefused(InvalidProposalTransitionException::class, 'invalid_status', fn () => $this->service()->returnToDraft($reviewer->id, ProposalType::Event, $draft->id, 'Otra vez'));
    }

    public function test_a_failing_approval_step_approves_nothing(): void
    {
        Event::fake(self::DOMAIN_EVENTS);
        $this->failingApprovalStep();
        $pending = EventModel::factory()->proposal(ProposalStatus::PendingReview)->create();

        try {
            $this->service()->approve($this->userWith(['events.review'])->id, ProposalType::Event, $pending->id);
            $this->fail('The failing step should stop the approval');
        } catch (RuntimeException $e) {
            $this->assertSame('module request failed', $e->getMessage());
        }

        $event = $this->event($pending->id);
        $this->assertSame(ProposalStatus::PendingReview, $event->proposal_status);
        $this->assertFalse($event->is_published);
        Event::assertNotDispatched(ProposalApproved::class);
    }

    public function test_approval_steps_run_inside_the_approval(): void
    {
        $pending = EventModel::factory()->proposal(ProposalStatus::PendingReview)->create();
        $reviewer = $this->userWith(['events.review']);
        $calls = [];
        $this->app->instance('test.step', new class($calls) implements ProposalApprovalStepInterface
        {
            /** @param  array<int, array{ProposalType, string, ?string}>  $calls */
            public function __construct(public array &$calls) {}

            public function apply(ProposalType $type, string $id, ?string $reviewerId): void
            {
                $this->calls[] = [$type, $id, $reviewerId];
            }
        });
        $this->app->tag(['test.step'], ProposalApprovalStepInterface::TAG);

        $this->service()->approve($reviewer->id, ProposalType::Event, $pending->id);

        $this->assertSame([[ProposalType::Event, $pending->id, $reviewer->id]], $calls);
    }

    public function test_returning_sends_the_proposal_back_to_a_draft_with_the_reason(): void
    {
        Event::fake(self::DOMAIN_EVENTS);
        $author = UserModel::factory()->create();
        $reviewer = $this->userWith(['events.review']);
        $id = $this->service()->createEvent($author->id, $this->eventData());
        $this->service()->submit($author->id, ProposalType::Event, $id);

        $this->assertRefused(InvalidProposalTransitionException::class, 'reason_required', fn () => $this->service()->returnToDraft($reviewer->id, ProposalType::Event, $id, '  '));
        $this->service()->returnToDraft($reviewer->id, ProposalType::Event, $id, 'Falta el lugar');

        $event = $this->event($id);
        $this->assertSame(ProposalStatus::Draft, $event->proposal_status);
        $this->assertSame('Falta el lugar', $event->proposal_reason);
        $this->assertNotNull($event->submitted_at, 'still visible to the panel as returned');
        $this->assertNotNull(EventModel::query()->find($id));
        Event::assertDispatched(ProposalReturned::class, fn (ProposalReturned $e): bool => $e->reason === 'Falta el lugar' && $e->proposerId === $author->id);

        // The author corrects it and submits it again
        $this->service()->updateEvent($author->id, $id, $this->eventData('Jornada corregida'));
        $this->assertSame(ProposalStatus::PendingReview, $this->service()->submit($author->id, ProposalType::Event, $id));
    }

    public function test_rejecting_closes_the_proposal_with_the_reason(): void
    {
        Event::fake(self::DOMAIN_EVENTS);
        $author = UserModel::factory()->create();
        $pending = ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $author)->create();
        $reviewer = $this->userWith(['articles.review']);

        $this->service()->reject($reviewer->id, ProposalType::Article, $pending->id, 'No encaja');

        $article = $this->article($pending->id);
        $this->assertSame(ProposalStatus::Rejected, $article->proposal_status);
        $this->assertSame('No encaja', $article->proposal_reason);
        $this->assertFalse($article->is_published);
        Event::assertDispatched(ProposalRejected::class, fn (ProposalRejected $e): bool => $e->reason === 'No encaja' && $e->reviewerId === $reviewer->id);
    }

    public function test_a_proposal_awaiting_review_stays_reviewable_when_its_author_loses_access(): void
    {
        $author = UserModel::factory()->create();
        $id = $this->service()->createEvent($author->id, $this->eventData());
        $this->service()->submit($author->id, ProposalType::Event, $id);
        $this->configure(events: false);

        $this->service()->approve($this->userWith(['events.review'])->id, ProposalType::Event, $id);

        $this->assertTrue($this->event($id)->is_published);
    }

    // Accounts and purge

    public function test_deleting_the_unpublished_proposals_of_an_account_keeps_what_was_published(): void
    {
        $user = UserModel::factory()->create();
        $draft = EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create();
        $pending = ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $user)->create();
        $rejected = EventModel::factory()->proposal(ProposalStatus::Rejected, $user)->create();
        $approved = EventModel::factory()->proposal(ProposalStatus::Approved, $user)->create();
        $other = EventModel::factory()->proposal(ProposalStatus::Draft)->create();

        $this->assertSame(3, $this->service()->deleteUnpublishedOf($user->id));

        $remaining = EventModel::query()->includingUnsubmittedProposals()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$approved->id, $other->id], $remaining);
        $this->assertNull(ArticleModel::query()->includingUnsubmittedProposals()->find($pending->id));
        unset($draft, $rejected);
    }

    public function test_purging_deletes_proposals_rejected_longer_ago_than_the_retention(): void
    {
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(rejectedRetentionDays: 30));
        $old = EventModel::factory()->proposal(ProposalStatus::Rejected)->create(['reviewed_at' => now()->subDays(31)]);
        $recent = ArticleModel::factory()->proposal(ProposalStatus::Rejected)->create(['reviewed_at' => now()->subDays(29)]);
        $returned = EventModel::factory()->proposal(ProposalStatus::Draft, submitted: true)->create(['reviewed_at' => now()->subDays(90)]);

        $this->assertSame(1, $this->service()->purgeRejected());

        $this->assertNull(EventModel::query()->find($old->id));
        $this->assertNotNull(ArticleModel::query()->find($recent->id));
        $this->assertNotNull(EventModel::query()->find($returned->id));
    }

    private function failingApprovalStep(): void
    {
        $this->app->instance('test.failing-step', new class implements ProposalApprovalStepInterface
        {
            public function apply(ProposalType $type, string $id, ?string $reviewerId): void
            {
                throw new RuntimeException('module request failed');
            }
        });
        $this->app->tag(['test.failing-step'], ProposalApprovalStepInterface::TAG);
    }
}
