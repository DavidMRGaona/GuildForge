<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Application\Content\Services\EditorImageUploadAuthorizerInterface;
use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Proposing events and articles from the web and following them in «Mis propuestas» (core#579):
 * who may, which fields are read, what each action does and what stays out of other people's reach.
 */
final class ProposalWebFormsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        $this->configure();
    }

    private function configure(bool $events = true, bool $articles = true, bool $showProposer = false, int $maxDrafts = 5): void
    {
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(enabled: $events, showProposer: $showProposer),
            articles: new ProposalTypeSettingsDTO(enabled: $articles),
            maxOpenDrafts: $maxDrafts,
        ));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventForm(array $overrides = []): array
    {
        return [
            'title' => 'Jornada de rol',
            'description' => '<p>Ven a jugar</p><script>alert(1)</script>',
            'location' => 'Local de la asociación',
            'start_date' => '2026-12-05',
            'start_time' => '17:00',
            'end_date' => '2026-12-05',
            'end_time' => '21:30',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function articleForm(array $overrides = []): array
    {
        return [
            'title' => 'Crónica del torneo',
            'excerpt' => 'Un resumen',
            'content' => '<p>Fue genial</p>',
            ...$overrides,
        ];
    }

    private function event(string $id): EventModel
    {
        return EventModel::query()->includingUnsubmittedProposals()->findOrFail($id);
    }

    // ---- access ----

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/propuestas/eventos/nuevo')->assertRedirect('/iniciar-sesion');
        $this->post('/propuestas/articulos', $this->articleForm())->assertRedirect('/iniciar-sesion');
    }

    public function test_the_forms_answer_403_when_proposals_are_off_for_the_type(): void
    {
        $this->configure(events: false, articles: true);
        $user = UserModel::factory()->create();

        $this->actingAs($user)->get('/propuestas/eventos/nuevo')->assertForbidden();
        $this->actingAs($user)->post('/propuestas/eventos', $this->eventForm())->assertForbidden();
        $this->actingAs($user)->get('/propuestas/articulos/nuevo')->assertOk();
        $this->assertSame(0, EventModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_an_unverified_account_may_not_propose(): void
    {
        $user = UserModel::factory()->unverified()->create();

        $this->actingAs($user)->get('/propuestas/articulos/nuevo')->assertForbidden();
    }

    // ---- events ----

    public function test_it_saves_an_event_as_a_draft_reading_the_dates_in_the_display_zone(): void
    {
        $user = UserModel::factory()->create();
        // An additional tag: whoever proposes never picks the category (core#654)
        $category = TagModel::factory()->create(['applies_to' => ['events']]);
        $tag = TagModel::factory()->withParent($category)->create(['applies_to' => ['events']]);

        $this->actingAs($user)
            ->post('/propuestas/eventos', $this->eventForm(['tags' => [$tag->id]]))
            ->assertRedirect('/perfil?tab=proposals');

        $event = EventModel::query()->includingUnsubmittedProposals()->firstOrFail();
        $this->assertSame(ProposalStatus::Draft, $event->proposalStatus());
        $this->assertSame($user->id, $event->proposed_by);
        $this->assertFalse($event->is_published);
        $this->assertSame('Jornada de rol', $event->title);
        $this->assertStringNotContainsString('<script', (string) $event->description);
        // 17:00 in Madrid in December is 16:00 UTC
        $this->assertSame('2026-12-05 17:00', app(TimezoneServiceInterface::class)->toDisplay($event->start_date)->format('Y-m-d H:i'));
        $this->assertSame([$tag->id], $event->tags()->pluck('tags.id')->all());
    }

    public function test_it_ignores_every_field_outside_the_list(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        $this->actingAs($user)->post('/propuestas/eventos', $this->eventForm([
            'member_price' => 50,
            'non_member_price' => 80,
            'download_links' => [['label' => 'x', 'url' => 'https://example.com']],
            'slug' => 'colado',
            'is_published' => true,
            'proposal_status' => 'approved',
            'proposed_by' => $other->id,
            'reviewed_by' => $other->id,
        ]))->assertRedirect();

        $event = EventModel::query()->includingUnsubmittedProposals()->firstOrFail();
        $this->assertSame(ProposalStatus::Draft, $event->proposalStatus());
        $this->assertSame($user->id, $event->proposed_by);
        $this->assertNull($event->member_price);
        $this->assertNull($event->non_member_price);
        $this->assertNotSame('colado', $event->slug);
        $this->assertFalse($event->is_published);
        $this->assertNull($event->reviewed_by);
    }

    public function test_it_stores_the_image_in_the_folder_the_panel_uses(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->post('/propuestas/eventos', $this->eventForm(['image' => UploadedFile::fake()->image('cartel.png', 60, 40)]))
            ->assertRedirect();

        $path = (string) $this->event(EventModel::query()->includingUnsubmittedProposals()->firstOrFail()->id)->image_public_id;
        $this->assertMatchesRegularExpression('~^events/\d{4}/\d{2}/[0-9a-f-]{36}\.png$~', $path);
        Storage::disk('images')->assertExists($path);
    }

    public function test_it_refuses_an_image_that_is_not_one(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->post('/propuestas/eventos', $this->eventForm(['image' => UploadedFile::fake()->create('virus.svg', 3, 'image/svg+xml')]))
            ->assertSessionHasErrors('image');

        $this->assertSame([], Storage::disk('images')->allFiles());
    }

    public function test_the_end_cannot_be_before_the_start(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->post('/propuestas/eventos', $this->eventForm(['end_time' => '16:00']))
            ->assertSessionHasErrors('end_date');
    }

    public function test_it_sends_an_event_to_review_when_asked(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->post('/propuestas/eventos', $this->eventForm(['submit' => true]))
            ->assertRedirect('/perfil?tab=proposals')
            ->assertSessionHas('success');

        $event = EventModel::query()->includingUnsubmittedProposals()->firstOrFail();
        $this->assertSame(ProposalStatus::PendingReview, $event->proposalStatus());
        $this->assertNotNull($event->submitted_at);
    }

    public function test_a_draft_that_cannot_be_sent_stays_saved_with_the_reason(): void
    {
        $user = UserModel::factory()->create();
        EventModel::factory()->count(3)->proposal(ProposalStatus::PendingReview, $user, true)->create();

        $response = $this->actingAs($user)->post('/propuestas/eventos', $this->eventForm(['submit' => true]));

        $draft = EventModel::query()->includingUnsubmittedProposals()->where('proposal_status', 'draft')->firstOrFail();
        $response->assertRedirect(route('proposals.events.edit', $draft->id))->assertSessionHas('error');
    }

    public function test_the_open_drafts_cap_shows_a_message_instead_of_failing(): void
    {
        $this->configure(maxDrafts: 1);
        $user = UserModel::factory()->create();
        EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create();

        $this->actingAs($user)->from('/propuestas/eventos/nuevo')
            ->post('/propuestas/eventos', $this->eventForm())
            ->assertRedirect('/propuestas/eventos/nuevo')
            ->assertSessionHas('error');
        $this->assertSame(1, EventModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_the_author_edits_a_draft_and_keeps_or_removes_its_image(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create(['image_public_id' => 'events/2026/11/a.jpg']);

        $this->actingAs($user)->get("/propuestas/eventos/{$event->id}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Proposals/EventForm')->where('proposal.id', $event->id)->where('proposal.status', 'draft'));

        $this->actingAs($user)->put("/propuestas/eventos/{$event->id}", $this->eventForm(['title' => 'Otro título']))->assertRedirect('/perfil?tab=proposals');
        $this->assertSame('Otro título', $this->event($event->id)->title);
        $this->assertSame('events/2026/11/a.jpg', $this->event($event->id)->image_public_id);

        $this->actingAs($user)->put("/propuestas/eventos/{$event->id}", $this->eventForm(['remove_image' => true]));
        $this->assertNull($this->event($event->id)->image_public_id);
    }

    public function test_someone_elses_proposal_is_a_404_everywhere(): void
    {
        $owner = UserModel::factory()->create();
        $intruder = UserModel::factory()->create();
        $event = EventModel::factory()->proposal(ProposalStatus::Draft, $owner)->create();
        $article = ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $owner)->create();

        $this->actingAs($intruder)->get("/propuestas/eventos/{$event->id}/editar")->assertNotFound();
        $this->actingAs($intruder)->put("/propuestas/eventos/{$event->id}", $this->eventForm())->assertNotFound();
        $this->actingAs($intruder)->post("/propuestas/eventos/{$event->id}/enviar")->assertNotFound();
        $this->actingAs($intruder)->delete("/propuestas/eventos/{$event->id}")->assertNotFound();
        $this->actingAs($intruder)->post("/propuestas/articulos/{$article->id}/retirar")->assertNotFound();
        $this->assertSame(ProposalStatus::Draft, $this->event($event->id)->proposalStatus());
    }

    public function test_a_panel_event_is_not_a_proposal(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        $this->actingAs($user)->get("/propuestas/eventos/{$event->id}/editar")->assertNotFound();
        $this->actingAs($user)->delete("/propuestas/eventos/{$event->id}")->assertNotFound();
    }

    public function test_a_proposal_in_review_cannot_be_edited(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->proposal(ProposalStatus::PendingReview, $user, true)->create();

        $this->actingAs($user)->get("/propuestas/eventos/{$event->id}/editar")
            ->assertRedirect('/perfil?tab=proposals')->assertSessionHas('error');
        $this->actingAs($user)->put("/propuestas/eventos/{$event->id}", $this->eventForm(['title' => 'Colado']))
            ->assertRedirect('/perfil?tab=proposals');
        $this->assertNotSame('Colado', $this->event($event->id)->title);
    }

    // ---- articles ----

    public function test_it_saves_an_article_with_a_server_made_slug_and_sanitised_content(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->post('/propuestas/articulos', $this->articleForm([
                'content' => '<p>Hola</p><script>alert(1)</script>',
                'slug' => 'colado',
                'author_id' => UserModel::factory()->create()->id,
                'published_at' => '2020-01-01',
            ]))
            ->assertRedirect('/perfil?tab=proposals');

        $article = ArticleModel::query()->includingUnsubmittedProposals()->firstOrFail();
        $this->assertSame('cronica-del-torneo', $article->slug);
        $this->assertSame($user->id, $article->author_id);
        $this->assertSame($user->id, $article->proposed_by);
        $this->assertStringNotContainsString('<script', (string) $article->content);
        $this->assertNull($article->published_at);
        $this->assertFalse($article->is_published);
    }

    public function test_an_article_needs_content(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->post('/propuestas/articulos', $this->articleForm(['content' => '<p></p>']))
            ->assertSessionHas('error');
    }

    // ---- actions ----

    public function test_the_author_sends_withdraws_and_deletes(): void
    {
        $user = UserModel::factory()->create();
        $article = ArticleModel::factory()->proposal(ProposalStatus::Draft, $user)->create();

        $this->actingAs($user)->post("/propuestas/articulos/{$article->id}/enviar")->assertRedirect('/perfil?tab=proposals')->assertSessionHas('success');
        $this->assertSame(ProposalStatus::PendingReview, ArticleModel::query()->includingUnsubmittedProposals()->findOrFail($article->id)->proposalStatus());

        $this->actingAs($user)->post("/propuestas/articulos/{$article->id}/retirar")->assertSessionHas('success');
        $this->assertSame(ProposalStatus::Draft, ArticleModel::query()->includingUnsubmittedProposals()->findOrFail($article->id)->proposalStatus());

        $this->actingAs($user)->delete("/propuestas/articulos/{$article->id}")->assertRedirect('/perfil?tab=proposals');
        $this->assertSame(0, ArticleModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_an_action_the_status_does_not_allow_shows_a_message(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->proposal(ProposalStatus::PendingReview, $user, true)->create();

        $this->actingAs($user)->delete("/propuestas/eventos/{$event->id}")
            ->assertRedirect('/perfil?tab=proposals')->assertSessionHas('error');
        $this->assertNotNull(EventModel::query()->find($event->id));
    }

    public function test_an_unknown_type_is_a_404(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->post('/propuestas/galerias/'.fake()->uuid().'/enviar')->assertNotFound();
    }

    public function test_a_person_who_lost_access_can_still_withdraw_and_delete(): void
    {
        $user = UserModel::factory()->create();
        $pending = EventModel::factory()->proposal(ProposalStatus::PendingReview, $user, true)->create();
        $rejected = ArticleModel::factory()->proposal(ProposalStatus::Rejected, $user, true)->create();
        $this->configure(events: false, articles: false);

        $this->actingAs($user)->post("/propuestas/eventos/{$pending->id}/retirar")->assertSessionHas('success');
        $this->actingAs($user)->delete("/propuestas/articulos/{$rejected->id}")->assertSessionHas('success');
        $this->actingAs($user)->post("/propuestas/eventos/{$pending->id}/enviar")->assertForbidden();
    }

    // ---- profile, buttons, «Propuesto por» ----

    public function test_the_profile_lists_only_the_persons_proposals_with_their_limits(): void
    {
        $user = UserModel::factory()->create();
        $mine = EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create();
        EventModel::factory()->proposal(ProposalStatus::Draft, UserModel::factory()->create())->create();
        ArticleModel::factory()->proposal(ProposalStatus::Rejected, $user, true)->create();

        $this->actingAs($user)->get('/perfil?tab=proposals')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('proposals.available', true)
                ->where('proposals.canPropose.event', true)
                ->has('proposals.items', 2)
                ->where('proposals.limits.draftsUsed', 1)
                ->where('proposals.limits.pendingUsed', 0)
                ->where('proposals.items', fn (mixed $items): bool => in_array($mine->id, array_column($items instanceof Collection ? $items->all() : (array) $items, 'id'), true))
        );
    }

    public function test_the_tab_is_not_available_without_access_nor_proposals(): void
    {
        $this->configure(events: false, articles: false);

        $this->actingAs(UserModel::factory()->create())->get('/perfil')->assertInertia(
            fn (Assert $page) => $page->where('proposals.available', false)->where('proposals.items', [])
        );
    }

    public function test_the_listings_offer_the_button_only_to_who_may_propose(): void
    {
        $this->configure(events: true, articles: false);

        $this->get('/eventos')->assertInertia(fn (Assert $page) => $page->where('canPropose', false));
        $user = UserModel::factory()->create();
        $this->actingAs($user)->get('/eventos')->assertInertia(fn (Assert $page) => $page->where('canPropose', true));
        $this->actingAs($user)->get('/articulos')->assertInertia(fn (Assert $page) => $page->where('canPropose', false));
    }

    public function test_the_event_page_says_who_proposed_it_when_the_setting_is_on(): void
    {
        $proposer = UserModel::factory()->create(['name' => 'Ana Pérez', 'display_name' => 'Ana']);
        $event = EventModel::factory()->proposal(ProposalStatus::Approved, $proposer)->create(['is_published' => true]);

        $this->get("/eventos/{$event->slug}")->assertInertia(fn (Assert $page) => $page->where('proposedBy', null));

        $this->configure(showProposer: true);
        $this->get("/eventos/{$event->slug}")->assertInertia(fn (Assert $page) => $page->where('proposedBy', 'Ana'));

        $panelEvent = EventModel::factory()->published()->create();
        $this->get("/eventos/{$panelEvent->slug}")->assertInertia(fn (Assert $page) => $page->where('proposedBy', null));
    }

    // ---- editor image uploads ----

    public function test_whoever_may_propose_articles_may_upload_editor_images(): void
    {
        $authorizer = app(EditorImageUploadAuthorizerInterface::class);
        $user = UserModel::factory()->create();

        $this->assertTrue($authorizer->canUpload($user, EditorImageOrigin::Articles));
        $this->assertFalse($authorizer->canUpload($user, EditorImageOrigin::Events));

        $this->configure(articles: false);
        $this->assertFalse(app(EditorImageUploadAuthorizerInterface::class)->canUpload($user, EditorImageOrigin::Articles));
    }

    public function test_whoever_creates_articles_in_the_panel_keeps_uploading(): void
    {
        $this->configure(articles: false);

        $this->assertTrue(app(EditorImageUploadAuthorizerInterface::class)
            ->canUpload(UserModel::factory()->admin()->create(), EditorImageOrigin::Articles));
    }

    public function test_a_proposer_uploads_through_the_editor_endpoint(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->postJson('/editor/imagenes/articulos', ['image' => UploadedFile::fake()->image('a.png')])
            ->assertCreated();
    }
}
