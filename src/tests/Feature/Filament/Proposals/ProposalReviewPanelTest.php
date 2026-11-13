<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Proposals;

use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Filament\ProposalModuleRelationManagerFixture;
use Tests\TestCase;

/**
 * Reviewing web proposals in the panel (core#575): the lists show what awaits review, the record
 * shows what the author wrote for the administration, and approving, returning or rejecting go
 * through the proposals service, never around it.
 */
final class ProposalReviewPanelTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        EventResource::clearExtendedFormSections();
        EventResource::clearExtendedRelations();
    }

    protected function tearDown(): void
    {
        EventResource::clearExtendedFormSections();
        EventResource::clearExtendedRelations();

        parent::tearDown();
    }

    public function test_the_in_review_tab_lists_only_proposals_awaiting_review_with_their_count(): void
    {
        $this->actingAs($this->eventReviewer());
        $pending = $this->event(ProposalStatus::PendingReview);
        $otherPending = $this->event(ProposalStatus::PendingReview);
        $returned = $this->event(ProposalStatus::Draft, submitted: true);
        $panelMade = $this->event();

        $list = Livewire::test(ListEvents::class)
            ->set('activeTab', 'pending')
            ->assertCanSeeTableRecords([$pending, $otherPending])
            ->assertCanNotSeeTableRecords([$returned, $panelMade]);

        $instance = $list->instance();
        $this->assertInstanceOf(ListEvents::class, $instance);
        $this->assertSame('2', (string) $instance->getTabs()['pending']->getBadge());
    }

    public function test_drafts_never_submitted_never_reach_the_panel(): void
    {
        $this->actingAs($this->eventReviewer());
        $unsubmitted = $this->event(ProposalStatus::Draft);
        $returned = $this->event(ProposalStatus::Draft, submitted: true);

        Livewire::test(ListEvents::class)
            ->assertCanSeeTableRecords([$returned])
            ->assertCanNotSeeTableRecords([$unsubmitted]);
    }

    public function test_filters_show_returned_and_rejected_proposals(): void
    {
        $this->actingAs($this->eventReviewer());
        $returned = $this->event(ProposalStatus::Draft, submitted: true);
        $rejected = $this->event(ProposalStatus::Rejected);
        $pending = $this->event(ProposalStatus::PendingReview);

        Livewire::test(ListEvents::class)
            ->filterTable('proposal_status', ProposalStatus::Draft->value)
            ->assertCanSeeTableRecords([$returned])
            ->assertCanNotSeeTableRecords([$rejected, $pending])
            ->filterTable('proposal_status', ProposalStatus::Rejected->value)
            ->assertCanSeeTableRecords([$rejected])
            ->assertCanNotSeeTableRecords([$returned, $pending]);
    }

    public function test_the_status_and_proposer_columns_name_the_review(): void
    {
        $this->actingAs($this->eventReviewer());
        $author = UserModel::factory()->create(['display_name' => 'Persona que propone']);
        $pending = $this->event(ProposalStatus::PendingReview, by: $author);
        $panelMade = $this->event();

        Livewire::test(ListEvents::class)
            ->assertTableColumnFormattedStateSet('proposal_status', 'En revisión', $pending)
            ->assertTableColumnStateSet('proposer.display_name', 'Persona que propone', $pending)
            ->assertTableColumnStateNotSet('proposal_status', ProposalStatus::PendingReview, $panelMade);
    }

    public function test_the_navigation_badge_counts_pending_proposals_for_reviewers_only(): void
    {
        $this->event(ProposalStatus::PendingReview);
        $this->event(ProposalStatus::PendingReview);
        $this->event(ProposalStatus::Draft, submitted: true);
        $this->article(ProposalStatus::PendingReview);

        $this->actingAs($this->eventReviewer());
        $this->assertSame('2', EventResource::getNavigationBadge());
        $this->assertNull(ArticleResource::getNavigationBadge());

        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update']));
        $this->assertNull(EventResource::getNavigationBadge());
    }

    public function test_the_navigation_badge_is_empty_when_nothing_awaits_review(): void
    {
        $this->event(ProposalStatus::Rejected);
        $this->actingAs($this->eventReviewer());

        $this->assertNull(EventResource::getNavigationBadge());
    }

    public function test_the_record_shows_the_authors_note_who_proposed_it_and_the_last_reason(): void
    {
        $this->actingAs($this->eventReviewer());
        $author = UserModel::factory()->create(['display_name' => 'Persona que propone']);
        $event = $this->event(ProposalStatus::PendingReview, by: $author, attributes: [
            'proposal_note' => 'Necesitamos dos mesas grandes.',
            'proposal_reason' => 'Falta la hora de fin.',
        ]);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertOk()
            ->assertSee('Necesitamos dos mesas grandes.')
            ->assertSee('Persona que propone')
            ->assertSee('Falta la hora de fin.')
            ->assertSee('En revisión');
    }

    public function test_events_created_in_the_panel_show_no_proposal_section(): void
    {
        $this->actingAs($this->eventReviewer());
        $event = $this->event();

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertOk()
            ->assertDontSee(__('filament.proposals.section.heading'))
            ->assertFormFieldIsEnabled('is_published');
    }

    public function test_the_published_toggle_is_disabled_until_the_proposal_is_approved(): void
    {
        $this->actingAs($this->eventReviewer());

        Livewire::test(EditEvent::class, ['record' => $this->event(ProposalStatus::PendingReview)->getRouteKey()])
            ->assertFormFieldIsDisabled('is_published');
        Livewire::test(EditEvent::class, ['record' => $this->event(ProposalStatus::Rejected)->getRouteKey()])
            ->assertFormFieldIsDisabled('is_published');
        Livewire::test(EditEvent::class, ['record' => $this->event(ProposalStatus::Approved)->getRouteKey()])
            ->assertFormFieldIsEnabled('is_published');
    }

    public function test_a_reviewer_may_retouch_a_proposal_in_review_without_publishing_it(): void
    {
        $this->actingAs($this->eventReviewer());
        $event = $this->event(ProposalStatus::PendingReview);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['title' => 'Título retocado', 'is_published' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();
        $this->assertSame('Título retocado', $event->title);
        $this->assertFalse($event->is_published);
        $this->assertSame(ProposalStatus::PendingReview, $event->proposalStatus());
    }

    public function test_review_actions_show_only_to_reviewers_while_the_proposal_awaits_review(): void
    {
        $pending = $this->event(ProposalStatus::PendingReview);

        $this->actingAs($this->eventReviewer());
        Livewire::test(EditEvent::class, ['record' => $pending->getRouteKey()])
            ->assertActionVisible('approveProposal')
            ->assertActionVisible('returnProposal')
            ->assertActionVisible('rejectProposal');

        Livewire::test(EditEvent::class, ['record' => $this->event(ProposalStatus::Rejected)->getRouteKey()])
            ->assertActionHidden('approveProposal')
            ->assertActionHidden('returnProposal')
            ->assertActionHidden('rejectProposal');

        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])
            ->assertActionHidden('approveProposal');

        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update', 'articles.review']));
        Livewire::test(EditEvent::class, ['record' => $pending->getRouteKey()])
            ->assertActionHidden('approveProposal')
            ->assertActionHidden('returnProposal')
            ->assertActionHidden('rejectProposal');
    }

    public function test_approving_publishes_the_proposal_with_the_edits_on_screen(): void
    {
        $reviewer = $this->eventReviewer();
        $this->actingAs($reviewer);
        $event = $this->event(ProposalStatus::PendingReview);

        $page = Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['title' => 'Título final'])
            ->callAction('approveProposal')
            ->assertHasNoActionErrors()
            ->assertNotified(__('filament.proposals.actions.approve.done'));

        $event->refresh();
        // The page reloads, so the toggle, the actions and the module tabs follow the new status
        $page->assertRedirect(EventResource::getUrl('edit', ['record' => $event]));
        $this->assertSame(ProposalStatus::Approved, $event->proposalStatus());
        $this->assertTrue($event->is_published);
        $this->assertSame('Título final', $event->title);
        $this->assertSame($reviewer->id, $event->reviewed_by);
    }

    public function test_returning_needs_a_reason_and_sends_the_proposal_back_to_a_draft(): void
    {
        $this->actingAs($this->eventReviewer());
        $event = $this->event(ProposalStatus::PendingReview);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->callAction('returnProposal', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
        $this->assertSame(ProposalStatus::PendingReview, $event->refresh()->proposalStatus());

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->callAction('returnProposal', data: ['reason' => 'Añade el lugar.'])
            ->assertHasNoActionErrors()
            ->assertNotified(__('filament.proposals.actions.return.done'));

        $event->refresh();
        $this->assertSame(ProposalStatus::Draft, $event->proposalStatus());
        $this->assertSame('Añade el lugar.', $event->proposal_reason);
        $this->assertFalse($event->is_published);
    }

    public function test_rejecting_needs_a_reason_and_the_confirmation_checkbox(): void
    {
        $this->actingAs($this->eventReviewer());
        $event = $this->event(ProposalStatus::PendingReview);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->callAction('rejectProposal', data: ['reason' => '', 'confirmation' => false])
            ->assertHasActionErrors(['reason' => 'required', 'confirmation' => 'accepted']);
        $this->assertSame(ProposalStatus::PendingReview, $event->refresh()->proposalStatus());

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->callAction('rejectProposal', data: ['reason' => 'No encaja con la asociación.', 'confirmation' => true])
            ->assertHasNoActionErrors()
            ->assertNotified(__('filament.proposals.actions.reject.done'));

        $event->refresh();
        $this->assertSame(ProposalStatus::Rejected, $event->proposalStatus());
        $this->assertSame('No encaja con la asociación.', $event->proposal_reason);
    }

    public function test_a_proposal_reviewed_meanwhile_can_no_longer_be_approved_from_the_open_page(): void
    {
        $this->actingAs($this->eventReviewer());
        $event = $this->event(ProposalStatus::PendingReview);
        $page = Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()]);

        // Someone else rejected it while this page was open
        EventModel::query()->whereKey($event->id)->update(['proposal_status' => ProposalStatus::Rejected->value]);

        // The action reloads the record and finds it no longer awaits review: it does not run
        $page->callAction('approveProposal')
            ->assertNotNotified(__('filament.proposals.actions.approve.done'))
            ->assertNoRedirect();
        $this->assertSame(ProposalStatus::Rejected, $event->refresh()->proposalStatus());
        $this->assertFalse($event->is_published);
    }

    public function test_a_permission_lost_after_opening_the_page_hides_the_actions_again(): void
    {
        $reviewer = $this->eventReviewer();
        $this->actingAs($reviewer);
        $event = $this->event(ProposalStatus::PendingReview);
        $page = Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()]);

        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update']));

        $page->assertActionHidden('rejectProposal');
        $this->assertSame(ProposalStatus::PendingReview, $event->refresh()->proposalStatus());
    }

    public function test_an_approval_step_that_fails_keeps_the_proposal_in_review_and_says_so(): void
    {
        $this->actingAs($this->eventReviewer());
        $event = $this->event(ProposalStatus::PendingReview);
        $service = Mockery::mock(ProposalServiceInterface::class);
        $service->shouldReceive('approve')->once()->andThrow(new RuntimeException('A module request failed.'));
        $this->app->instance(ProposalServiceInterface::class, $service);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->callAction('approveProposal')
            ->assertNotified(__('filament.proposals.errors.unexpected'))
            ->assertNoRedirect();
        $this->assertSame(ProposalStatus::PendingReview, $event->refresh()->proposalStatus());
    }

    public function test_the_service_refusal_is_mapped_to_its_message(): void
    {
        $this->actingAs($this->eventReviewer());
        $event = $this->event(ProposalStatus::PendingReview);
        $service = Mockery::mock(ProposalServiceInterface::class);
        $service->shouldReceive('reject')
            ->once()
            ->with(Mockery::any(), ProposalType::Event, $event->id, 'Motivo')
            ->andThrow(ProposalNotAllowedException::because('review_denied'));
        $this->app->instance(ProposalServiceInterface::class, $service);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->callAction('rejectProposal', data: ['reason' => 'Motivo', 'confirmation' => true])
            ->assertNotified(__('filament.proposals.errors.review_denied'));
    }

    public function test_module_sections_and_relations_stay_hidden_until_the_event_is_approved(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        EventResource::extendFormSections([
            'test.module' => Section::make('Sección del módulo')->schema([TextInput::make('module_field')]),
        ]);
        EventResource::extendRelations(['module-tab' => ProposalModuleRelationManagerFixture::class]);

        foreach ([ProposalStatus::PendingReview, ProposalStatus::Draft, ProposalStatus::Rejected] as $status) {
            $page = Livewire::test(EditEvent::class, ['record' => $this->event($status, submitted: true)->getRouteKey()])
                ->assertFormFieldIsHidden('module_field');
            $this->assertNotContains(ProposalModuleRelationManagerFixture::class, $this->relationManagersOf($page->instance()), $status->value);
        }

        foreach ([$this->event(ProposalStatus::Approved), $this->event()] as $event) {
            $page = Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
                ->assertFormFieldIsVisible('module_field');
            $this->assertContains(ProposalModuleRelationManagerFixture::class, $this->relationManagersOf($page->instance()));
        }
    }

    public function test_article_proposals_are_reviewed_the_same_way(): void
    {
        $reviewer = $this->editorWithPermissions(['articles.view_any', 'articles.update', 'articles.review']);
        $this->actingAs($reviewer);
        $pending = $this->article(ProposalStatus::PendingReview, attributes: ['proposal_note' => 'Nota para quien revisa.']);
        $returned = $this->article(ProposalStatus::Draft, submitted: true);

        $list = Livewire::test(ListArticles::class)
            ->set('activeTab', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$returned]);
        $instance = $list->instance();
        $this->assertInstanceOf(ListArticles::class, $instance);
        $this->assertSame('1', (string) $instance->getTabs()['pending']->getBadge());
        $this->assertSame('1', ArticleResource::getNavigationBadge());

        Livewire::test(EditArticle::class, ['record' => $pending->getRouteKey()])
            ->assertSee('Nota para quien revisa.')
            ->assertFormFieldIsDisabled('is_published')
            ->callAction('approveProposal')
            ->assertHasNoActionErrors();

        $pending->refresh();
        $this->assertSame(ProposalStatus::Approved, $pending->proposalStatus());
        $this->assertTrue($pending->is_published);
        $this->assertNotNull($pending->published_at);
    }

    /**
     * @return array<mixed>
     */
    private function relationManagersOf(object $page): array
    {
        $this->assertInstanceOf(EditEvent::class, $page);

        return $page->getRelationManagers();
    }

    private function eventReviewer(): UserModel
    {
        return $this->editorWithPermissions(['events.view_any', 'events.update', 'events.review']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(?ProposalStatus $status = null, ?UserModel $by = null, bool $submitted = false, array $attributes = []): EventModel
    {
        $factory = EventModel::factory();

        if ($status instanceof ProposalStatus) {
            $factory = $factory->proposal($status, $by, $submitted);
        }

        $event = $factory->create([
            'start_date' => now()->addDays(10)->setTime(10, 0),
            'end_date' => now()->addDays(10)->setTime(20, 0),
        ]);
        // Review columns are not fillable: only the proposals flow writes them
        $event->forceFill($attributes)->saveQuietly();
        $event->tags()->attach(TagModel::factory()->forEvents()->create());

        return $event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function article(?ProposalStatus $status = null, bool $submitted = false, array $attributes = []): ArticleModel
    {
        $factory = ArticleModel::factory();

        if ($status instanceof ProposalStatus) {
            $factory = $factory->proposal($status, submitted: $submitted);
        }

        $article = $factory->create();
        $article->forceFill($attributes)->saveQuietly();
        $article->tags()->attach(TagModel::factory()->forArticles()->create());

        return $article;
    }
}
