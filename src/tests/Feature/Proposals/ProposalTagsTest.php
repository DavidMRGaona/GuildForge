<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Application\Proposals\DTOs\ArticleProposalDataDTO;
use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * Tags of web proposals follow the panel's rule (core#654): whoever proposes picks only additional
 * tags (subtags) and whoever reviews picks the category, so approving never drops a tag. Whoever
 * may publish their proposals directly reviews them too, so they pick the category on the web.
 */
final class ProposalTagsTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private TagModel $eventCategory;

    private TagModel $eventSubtag;

    private TagModel $articleCategory;

    private TagModel $articleSubtag;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(enabled: true),
            articles: new ProposalTypeSettingsDTO(enabled: true),
        ));

        $this->eventCategory = TagModel::factory()->forEvents()->create(['name' => 'Rol']);
        $this->eventSubtag = TagModel::factory()->forEvents()->withParent($this->eventCategory)->create(['name' => 'Partida corta']);
        $this->articleCategory = TagModel::factory()->forArticles()->create(['name' => 'Crónicas']);
        $this->articleSubtag = TagModel::factory()->forArticles()->withParent($this->articleCategory)->create(['name' => 'Torneos']);
    }

    // ---- the form ----

    public function test_the_forms_offer_only_additional_tags_to_whoever_proposes(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->get('/propuestas/eventos/nuevo')->assertInertia(fn (Assert $page) => $page
            ->where('tags', fn (Collection $tags): bool => $tags->pluck('id')->all() === [$this->eventSubtag->id])
            ->where('categories', []));

        $this->actingAs($user)->get('/propuestas/articulos/nuevo')->assertInertia(fn (Assert $page) => $page
            ->where('tags', fn (Collection $tags): bool => $tags->pluck('id')->all() === [$this->articleSubtag->id])
            ->where('categories', []));
    }

    public function test_whoever_may_publish_directly_is_offered_the_categories_too(): void
    {
        $user = $this->userWith(['events.publish_proposals_directly']);

        $this->actingAs($user)->get('/propuestas/eventos/nuevo')->assertInertia(fn (Assert $page) => $page
            ->where('categories', fn (Collection $categories): bool => $categories->pluck('id')->all() === [$this->eventCategory->id])
            ->where('tags', fn (Collection $tags): bool => $tags->pluck('id')->all() === [$this->eventSubtag->id]));

        // The permission of one type does not cover the other
        $this->actingAs($user)->get('/propuestas/articulos/nuevo')->assertInertia(fn (Assert $page) => $page
            ->where('categories', []));
    }

    // ---- what is stored ----

    public function test_a_proposer_never_sets_a_category_even_when_forged(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->post('/propuestas/eventos', $this->eventForm([
            'tags' => [$this->eventSubtag->id, $this->eventCategory->id],
            'category' => $this->eventCategory->id,
        ]))->assertRedirect('/perfil?tab=proposals');

        $this->actingAs($user)->post('/propuestas/articulos', $this->articleForm([
            'tags' => [$this->articleSubtag->id, $this->articleCategory->id],
            'category' => $this->articleCategory->id,
        ]))->assertRedirect('/perfil?tab=proposals');

        $this->assertSame([$this->eventSubtag->id], $this->tagIdsOf($this->onlyEvent()));
        $this->assertSame([$this->articleSubtag->id], $this->tagIdsOf($this->onlyArticle()));
    }

    public function test_editing_a_returned_proposal_keeps_the_category_the_reviewer_chose(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user)->post('/propuestas/eventos', $this->eventForm(['tags' => [$this->eventSubtag->id]]));
        $event = $this->onlyEvent();
        // What the panel leaves after a reviewer picks the category and returns it to a draft
        $event->tags()->attach($this->eventCategory->id);
        $event->forceFill(['submitted_at' => now()])->saveQuietly();

        $this->actingAs($user)->post('/propuestas/eventos/'.$event->id, $this->eventForm([
            '_method' => 'put',
            'title' => 'Jornada de rol corregida',
            'tags' => [],
        ]))->assertRedirect('/perfil?tab=proposals');

        $this->assertSame([$this->eventCategory->id], $this->tagIdsOf($event));
    }

    public function test_whoever_may_publish_directly_must_pick_a_category_to_send_it(): void
    {
        $user = $this->userWith(['events.publish_proposals_directly']);

        $this->actingAs($user)
            ->post('/propuestas/eventos', $this->eventForm(['submit' => true]))
            ->assertSessionHasErrors('category');

        $this->assertSame(0, EventModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_whoever_may_publish_directly_may_save_a_draft_without_a_category(): void
    {
        $user = $this->userWith(['events.publish_proposals_directly']);

        $this->actingAs($user)
            ->post('/propuestas/eventos', $this->eventForm())
            ->assertSessionHasNoErrors();

        $this->assertSame(ProposalStatus::Draft, $this->onlyEvent()->proposalStatus());
    }

    public function test_whoever_may_publish_directly_publishes_with_the_category_and_tags_chosen(): void
    {
        $user = $this->userWith(['events.publish_proposals_directly', 'articles.publish_proposals_directly']);

        $this->actingAs($user)->post('/propuestas/eventos', $this->eventForm([
            'submit' => true,
            'category' => $this->eventCategory->id,
            'tags' => [$this->eventSubtag->id],
        ]))->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/propuestas/articulos', $this->articleForm([
            'submit' => true,
            'category' => $this->articleCategory->id,
            'tags' => [$this->articleSubtag->id],
        ]))->assertSessionHasNoErrors();

        $event = $this->onlyEvent();
        $this->assertSame(ProposalStatus::Approved, $event->proposalStatus());
        $this->assertEqualsCanonicalizing([$this->eventCategory->id, $this->eventSubtag->id], $this->tagIdsOf($event));
        $article = $this->onlyArticle();
        $this->assertSame(ProposalStatus::Approved, $article->proposalStatus());
        $this->assertEqualsCanonicalizing([$this->articleCategory->id, $this->articleSubtag->id], $this->tagIdsOf($article));
    }

    public function test_a_category_must_be_one_of_the_type(): void
    {
        $user = $this->userWith(['events.publish_proposals_directly']);

        $this->actingAs($user)
            ->post('/propuestas/eventos', $this->eventForm(['submit' => true, 'category' => $this->articleCategory->id]))
            ->assertSessionHasErrors('category');

        $this->actingAs($user)
            ->post('/propuestas/eventos', $this->eventForm(['submit' => true, 'category' => $this->eventSubtag->id]))
            ->assertSessionHasErrors('category');
    }

    public function test_the_service_keeps_in_review_a_direct_publication_without_a_category(): void
    {
        $user = $this->userWith(['articles.publish_proposals_directly']);
        $service = app(ProposalServiceInterface::class);
        $id = $service->createArticle($user->id, new ArticleProposalDataDTO(title: 'Crónica', content: '<p>Fue genial</p>'));

        $this->assertSame(ProposalStatus::PendingReview, $service->submit($user->id, ProposalType::Article, $id));
        $this->assertFalse(ArticleModel::query()->findOrFail($id)->is_published);
    }

    // ---- reviewing ----

    public function test_approving_keeps_the_proposers_tags_and_the_reviewers_category(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user)->post('/propuestas/eventos', $this->eventForm(['submit' => true, 'tags' => [$this->eventSubtag->id]]));
        $this->actingAs($user)->post('/propuestas/articulos', $this->articleForm(['submit' => true, 'tags' => [$this->articleSubtag->id]]));
        $event = $this->onlyEvent();
        $article = $this->onlyArticle();

        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update', 'events.review', 'articles.view_any', 'articles.update', 'articles.review']));

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(['category_id' => null, 'additional_tag_ids' => [$this->eventSubtag->id]])
            ->fillForm(['category_id' => $this->eventCategory->id])
            ->callAction('approveProposal')
            ->assertHasNoActionErrors();

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['category_id' => $this->articleCategory->id])
            ->callAction('approveProposal')
            ->assertHasNoActionErrors();

        $this->assertSame(ProposalStatus::Approved, $event->refresh()->proposalStatus());
        $this->assertEqualsCanonicalizing([$this->eventCategory->id, $this->eventSubtag->id], $this->tagIdsOf($event));
        $this->assertSame(ProposalStatus::Approved, $article->refresh()->proposalStatus());
        $this->assertEqualsCanonicalizing([$this->articleCategory->id, $this->articleSubtag->id], $this->tagIdsOf($article));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventForm(array $overrides = []): array
    {
        return [
            'title' => 'Jornada de rol',
            'description' => '<p>Ven a jugar</p>',
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'start_time' => '17:00',
            'end_date' => now()->addDays(10)->format('Y-m-d'),
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

    private function onlyEvent(): EventModel
    {
        return EventModel::query()->includingUnsubmittedProposals()->sole();
    }

    private function onlyArticle(): ArticleModel
    {
        return ArticleModel::query()->includingUnsubmittedProposals()->sole();
    }

    /**
     * @return list<string>
     */
    private function tagIdsOf(EventModel|ArticleModel $record): array
    {
        /** @var list<string> $ids */
        $ids = $record->tags()->pluck('tags.id')->all();

        return $ids;
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
}
