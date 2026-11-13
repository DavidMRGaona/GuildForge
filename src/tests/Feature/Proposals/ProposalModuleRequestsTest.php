<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Filament\Proposals\ProposalRequestsSection;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventProposalRequestModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\ProposalToReviewNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Proposals\FixtureEventProposalRequest;
use Tests\TestCase;

/**
 * The requests a module adds to event proposals, through the whole path (core#581): offered on
 * the web form, checked on the server, stored with the draft, listed in «Mis propuestas»,
 * reviewed in the panel and applied, or not, on approval.
 */
final class ProposalModuleRequestsTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const string KEY = FixtureEventProposalRequest::KEY;

    protected function setUp(): void
    {
        parent::setUp();

        FixtureEventProposalRequest::reset();
        app(EventProposalRequestRegistryInterface::class)->clear();
        app(EventProposalRequestRegistryInterface::class)->register(FixtureEventProposalRequest::class, 'fixture');
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(enabled: true),
            articles: new ProposalTypeSettingsDTO(enabled: true),
        ));
    }

    protected function tearDown(): void
    {
        FixtureEventProposalRequest::reset();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $requests
     * @return array<string, mixed>
     */
    private function eventForm(array $requests): array
    {
        return [
            'title' => 'Jornada de rol',
            'description' => '<p>Ven a jugar</p>',
            'start_date' => '2026-12-05',
            'start_time' => '17:00',
            'end_date' => '2026-12-05',
            'end_time' => '21:30',
            'requests' => $requests,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function asked(array $values = ['capacity' => '20', 'waitlist' => '1', 'mode' => 'open'], string $wanted = '1'): array
    {
        return [self::KEY => ['wanted' => $wanted, 'values' => $values]];
    }

    private function proposal(): EventModel
    {
        return EventModel::query()->includingUnsubmittedProposals()->firstOrFail();
    }

    private function reviewer(): UserModel
    {
        return $this->editorWithPermissions(['events.view_any', 'events.update', 'events.review']);
    }

    /**
     * An event proposal in review whose author asked for the fixture request.
     */
    private function pendingProposal(): EventModel
    {
        $author = UserModel::factory()->create();
        $this->actingAs($author)->post('/propuestas/eventos', [...$this->eventForm($this->asked()), 'submit' => '1'])->assertRedirect('/perfil?tab=proposals');
        $event = $this->proposal();
        $event->tags()->attach(TagModel::factory()->forEvents()->create());

        return $event;
    }

    public function test_the_form_offers_the_active_requests_with_their_fields(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->get('/propuestas/eventos/nuevo')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proposals/EventForm')
                ->has('moduleRequests', 1)
                ->where('moduleRequests.0.key', self::KEY)
                ->where('moduleRequests.0.question', '¿Quieres inscripciones?')
                ->where('moduleRequests.0.fields.0.name', 'capacity')
                ->where('moduleRequests.0.fields.0.max', 500)
                ->where('moduleRequests.0.fields.1.visibleWhen', ['field' => 'capacity', 'values' => null])
                ->where('moduleRequests.0.fields.2.options', [['value' => 'open', 'label' => 'Cualquiera'], ['value' => 'members', 'label' => 'Solo socios']]));

        FixtureEventProposalRequest::$available = false;

        $this->get('/propuestas/eventos/nuevo')->assertInertia(fn (Assert $page) => $page->has('moduleRequests', 0));
    }

    public function test_the_draft_keeps_what_was_asked_and_mis_propuestas_lists_it(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->post('/propuestas/eventos', $this->eventForm($this->asked()))->assertRedirect('/perfil?tab=proposals');

        $row = EventProposalRequestModel::query()->firstOrFail();
        $this->assertSame(['capacity' => 20, 'waitlist' => true, 'mode' => 'open', 'note' => null], $row->requested_values);

        $this->get('/perfil?tab=proposals')->assertInertia(fn (Assert $page) => $page
            ->where('proposals.items.0.requests.0.key', self::KEY)
            ->where('proposals.items.0.requests.0.label', 'Inscripciones')
            ->where('proposals.items.0.requests.0.description', 'Inscripciones, aforo de 20, con lista de espera')
            ->where('proposals.items.0.requests.0.values.capacity', 20));

        $this->get('/propuestas/eventos/'.$row->event_id.'/editar')
            ->assertInertia(fn (Assert $page) => $page->where('proposal.requests.0.values.mode', 'open'));
    }

    public function test_values_are_checked_on_the_server_with_one_error_per_field(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->post('/propuestas/eventos', $this->eventForm($this->asked(['capacity' => '900', 'mode' => 'nobody'])))
            ->assertSessionHasErrors([
                'requests.'.self::KEY.'.capacity' => '«Aforo» debe ser como máximo 500.',
                'requests.'.self::KEY.'.mode' => 'Elige una de las opciones de «Quién se inscribe».',
            ]);

        $this->assertSame(0, EventModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_an_unticked_request_ignores_its_values_and_unknown_requests_are_dropped(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->post('/propuestas/eventos', $this->eventForm([
                ...$this->asked(['capacity' => 'nonsense'], wanted: '0'),
                'other-module.thing' => ['wanted' => '1', 'values' => ['x' => 'y']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, EventProposalRequestModel::query()->count());
    }

    public function test_reviewers_read_the_requests_in_the_notice(): void
    {
        Notification::fake();
        $reviewer = $this->reviewer();

        $this->pendingProposal();

        Notification::assertSentTo($reviewer, ProposalToReviewNotification::class, function (ProposalToReviewNotification $notice) use ($reviewer): bool {
            $lines = implode("\n", array_map(strval(...), $notice->toMail($reviewer)->introLines));

            return str_contains($lines, __('proposals.notices.review_requests'))
                && str_contains($lines, '- Inscripciones: Inscripciones, aforo de 20, con lista de espera');
        });
    }

    public function test_the_reviewer_adjusts_the_values_and_approving_applies_them(): void
    {
        $event = $this->pendingProposal();
        $this->actingAs($this->reviewer());
        $slot = ProposalRequestsSection::STATE.'.'.ProposalRequestsSection::slot(self::KEY);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertSee('Peticiones de módulos')
            ->assertFormSet([$slot.'.apply' => true, $slot.'.values.capacity' => 20])
            ->fillForm([$slot.'.values.capacity' => 35, $slot.'.values.mode' => 'members'])
            ->callAction('approveProposal')
            ->assertNotified(__('filament.proposals.actions.approve.done'));

        $this->assertSame(ProposalStatus::Approved, $event->refresh()->proposalStatus());
        $this->assertSame(['capacity' => 35, 'waitlist' => true, 'mode' => 'members', 'note' => null], FixtureEventProposalRequest::configOf($event->id));
    }

    public function test_a_value_the_request_refuses_stops_the_approval(): void
    {
        $event = $this->pendingProposal();
        $this->actingAs($this->reviewer());
        $slot = ProposalRequestsSection::STATE.'.'.ProposalRequestsSection::slot(self::KEY);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm([$slot.'.values.capacity' => 9999])
            ->callAction('approveProposal')
            ->assertNotNotified(__('filament.proposals.actions.approve.done'));

        $this->assertSame(ProposalStatus::PendingReview, $event->refresh()->proposalStatus());
        $this->assertNull(FixtureEventProposalRequest::configOf($event->id));
    }

    public function test_a_module_refusal_is_shown_and_nothing_is_approved(): void
    {
        $event = $this->pendingProposal();
        $this->actingAs($this->reviewer());
        FixtureEventProposalRequest::$failWith = ProposalRequestRefusedException::because('El espacio no está libre.');

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->callAction('approveProposal')
            ->assertNotified(__('filament.proposals.errors.request_failed', ['request' => 'Inscripciones']))
            ->assertNoRedirect();

        $this->assertSame(ProposalStatus::PendingReview, $event->refresh()->proposalStatus());
        $this->assertFalse($event->is_published);
    }

    public function test_a_request_of_an_inactive_module_can_be_unticked_to_approve_without_it(): void
    {
        $event = $this->pendingProposal();
        $this->actingAs($this->reviewer());
        $slot = ProposalRequestsSection::STATE.'.'.ProposalRequestsSection::slot(self::KEY);
        FixtureEventProposalRequest::$available = false;

        $page = Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertSee(__('filament.proposals.requests.inactive'))
            ->callAction('approveProposal')
            ->assertNotified(__('filament.proposals.errors.request_unavailable', ['request' => 'Inscripciones']));
        $this->assertSame(ProposalStatus::PendingReview, $event->refresh()->proposalStatus());

        $page->fillForm([$slot.'.apply' => false])
            ->callAction('approveProposal')
            ->assertNotified(__('filament.proposals.actions.approve.done'));

        $this->assertSame(ProposalStatus::Approved, $event->refresh()->proposalStatus());
        $this->assertNull(FixtureEventProposalRequest::configOf($event->id));
    }

    public function test_whoever_may_not_review_sees_the_requests_but_cannot_change_them(): void
    {
        $event = $this->pendingProposal();
        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update']));
        $slot = ProposalRequestsSection::STATE.'.'.ProposalRequestsSection::slot(self::KEY);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormFieldIsDisabled($slot.'.apply')
            ->set('data.'.$slot.'.apply', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(EventProposalRequestModel::query()->firstOrFail()->apply);
    }

    public function test_a_panel_event_has_no_requests_section(): void
    {
        $this->actingAs($this->reviewer());
        $event = EventModel::factory()->create();
        $event->tags()->attach(TagModel::factory()->forEvents()->create());

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertDontSee('Peticiones de módulos')
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
