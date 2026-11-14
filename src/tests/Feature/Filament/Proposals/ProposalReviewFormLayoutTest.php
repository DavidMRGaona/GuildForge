<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Proposals;

use App\Application\Proposals\DTOs\ArticleProposalDataDTO;
use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * The panel form of events and articles follows the web proposal form (core#655): the fields both
 * share come in the web's order, and what the web never asks for comes after them. While the record
 * is an unapproved proposal that part is boxed as «Lo completa quien revisa»; otherwise the same
 * fields stand loose, as any other field.
 */
final class ProposalReviewFormLayoutTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const array EVENT_ORDER = [
        'title', 'slug', 'start_date', 'end_date', 'location', 'description', 'image_public_id',
        'additional_tag_ids', 'category_id', 'member_price', 'non_member_price', 'download_links', 'is_published',
    ];

    private const array ARTICLE_ORDER = [
        'title', 'slug', 'excerpt', 'content', 'featured_image_public_id',
        'additional_tag_ids', 'category_id', 'author_id', 'is_published', 'published_at',
    ];

    private const array EVENT_BLOCK = ['category_id', 'member_price', 'non_member_price', 'download_links'];

    private const array ARTICLE_BLOCK = ['category_id', 'author_id'];

    protected function setUp(): void
    {
        parent::setUp();

        EventResource::clearExtendedFormSections();
        $this->actingAs($this->editorWithPermissions([
            'events.view_any', 'events.create', 'events.update', 'events.review',
            'articles.view_any', 'articles.create', 'articles.update', 'articles.review',
        ]));
    }

    protected function tearDown(): void
    {
        EventResource::clearExtendedFormSections();

        parent::tearDown();
    }

    public function test_the_event_form_follows_the_web_order_with_the_reviewers_part_last(): void
    {
        $this->assertSame(self::EVENT_ORDER, $this->order(Livewire::test(CreateEvent::class), self::EVENT_ORDER));
        $this->assertSame(self::EVENT_ORDER, $this->order($this->editEvent(ProposalStatus::PendingReview), self::EVENT_ORDER));
    }

    public function test_the_article_form_follows_the_web_order_with_the_reviewers_part_last(): void
    {
        $this->assertSame(self::ARTICLE_ORDER, $this->order(Livewire::test(CreateArticle::class), self::ARTICLE_ORDER));
        $this->assertSame(self::ARTICLE_ORDER, $this->order($this->editArticle(ProposalStatus::PendingReview), self::ARTICLE_ORDER));
    }

    public function test_the_place_of_an_event_goes_with_its_dates(): void
    {
        $dates = $this->form(Livewire::test(CreateEvent::class))->getComponent(
            static fn (mixed $component): bool => $component instanceof Section
                && array_key_exists('start_date', $component->getChildSchema()?->getFlatFields(withHidden: true) ?? []),
            withHidden: true,
        );

        $this->assertInstanceOf(Section::class, $dates);
        $this->assertSame('Fecha y lugar', $dates->getHeading());
        $this->assertSame(
            ['start_date', 'end_date', 'location'],
            array_keys($dates->getChildSchema()?->getFlatFields(withHidden: true) ?? []),
        );
    }

    public function test_an_event_proposal_boxes_what_the_reviewer_completes(): void
    {
        $page = $this->editEvent(ProposalStatus::PendingReview);
        $block = $this->block($page);

        $this->assertTrue($block->isContained());
        $this->assertSame(__('filament.proposals.reviewer_block.heading'), $block->getHeading());
        $this->assertSame(__('filament.proposals.reviewer_block.description'), $block->getDescription());
        $this->assertSame(self::EVENT_BLOCK, $this->fieldsIn($block, self::EVENT_BLOCK));
        $page->assertSee(__('filament.proposals.reviewer_block.heading'));
    }

    public function test_an_article_proposal_boxes_what_the_reviewer_completes(): void
    {
        $page = $this->editArticle(ProposalStatus::Draft, submitted: true);
        $block = $this->block($page);

        $this->assertTrue($block->isContained());
        $this->assertSame(__('filament.proposals.reviewer_block.heading'), $block->getHeading());
        $this->assertSame(self::ARTICLE_BLOCK, $this->fieldsIn($block, self::ARTICLE_BLOCK));
    }

    public function test_records_made_in_the_panel_and_approved_proposals_show_the_fields_loose(): void
    {
        foreach ([
            Livewire::test(CreateEvent::class),
            $this->editEvent(null),
            $this->editEvent(ProposalStatus::Approved),
            Livewire::test(CreateArticle::class),
            $this->editArticle(null),
        ] as $page) {
            $block = $this->block($page);
            $this->assertFalse($block->isContained());
            $this->assertNull($block->getHeading());
            $this->assertNull($block->getDescription());
            $page->assertDontSee(__('filament.proposals.reviewer_block.heading'));
        }
    }

    public function test_the_slug_says_it_comes_from_the_title(): void
    {
        foreach ([Livewire::test(CreateEvent::class), Livewire::test(CreateArticle::class)] as $page) {
            $page->assertSee(__('filament.proposals.slug_hint'));
        }
    }

    public function test_the_author_says_it_is_who_signs_the_article_on_the_site(): void
    {
        // Changing it never changes who proposed the article (proposed_by), only the byline
        Livewire::test(CreateArticle::class)->assertSee(__('filament.proposals.author_hint'));
        $this->editArticle(ProposalStatus::PendingReview)->assertSee(__('filament.proposals.author_hint'));
    }

    public function test_the_author_of_an_article_proposal_starts_as_its_proposer_and_can_be_found(): void
    {
        // A member without panel access and without a display name, as most proposers are
        $proposer = UserModel::factory()->create(['name' => 'Persona que propone', 'display_name' => null]);
        UserModel::factory()->count(3)->create();
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(enabled: false),
            articles: new ProposalTypeSettingsDTO(enabled: true),
        ));
        $id = app(ProposalServiceInterface::class)->createArticle($proposer->id, new ArticleProposalDataDTO(title: 'Crónica', content: '<p>Fue genial</p>'));
        $article = ArticleModel::query()->includingUnsubmittedProposals()->findOrFail($id);
        $article->forceFill(['proposal_status' => ProposalStatus::PendingReview, 'submitted_at' => now()])->saveQuietly();

        $page = Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->assertFormSet(['author_id' => $proposer->id]);

        $author = $this->form($page)->getFlatFields(withHidden: true)['author_id'] ?? null;
        $this->assertInstanceOf(Select::class, $author);
        $this->assertSame('Persona que propone', $author->getOptionLabel());
        $this->assertArrayHasKey($proposer->id, $author->getSearchResults('Persona que'));
    }

    /**
     * @return Testable<Component>
     */
    private function editEvent(?ProposalStatus $status): Testable
    {
        $factory = EventModel::factory();

        if ($status instanceof ProposalStatus) {
            $factory = $factory->proposal($status);
        }

        $event = $factory->create([
            'start_date' => now()->addDays(10)->setTime(10, 0),
            'end_date' => now()->addDays(10)->setTime(20, 0),
        ]);
        $event->tags()->attach(TagModel::factory()->forEvents()->create());

        return Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()]);
    }

    /**
     * @return Testable<Component>
     */
    private function editArticle(?ProposalStatus $status, bool $submitted = false): Testable
    {
        $factory = ArticleModel::factory();

        if ($status instanceof ProposalStatus) {
            $factory = $factory->proposal($status, submitted: $submitted);
        }

        $article = $factory->create();
        $article->tags()->attach(TagModel::factory()->forArticles()->create());

        return Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()]);
    }

    /**
     * @param  Testable<Component>  $page
     */
    private function form(Testable $page): Schema
    {
        $instance = $page->instance();
        $this->assertInstanceOf(HasSchemas::class, $instance);
        $form = $instance->getSchema('form');
        $this->assertInstanceOf(Schema::class, $form);

        return $form;
    }

    /**
     * @param  Testable<Component>  $page
     */
    private function block(Testable $page): Section
    {
        // The section holding the category: the part of the form the web never asks for
        $block = $this->form($page)->getComponent(
            static fn (mixed $component): bool => $component instanceof Section
                && array_key_exists('category_id', $component->getChildSchema()?->getFlatFields(withHidden: true) ?? []),
            withHidden: true,
        );
        $this->assertInstanceOf(Section::class, $block);

        return $block;
    }

    /**
     * The fields of the form among the given names, in the order the form draws them.
     *
     * @param  Testable<Component>  $page
     * @param  list<string>  $names
     * @return list<string>
     */
    private function order(Testable $page, array $names): array
    {
        return array_values(array_intersect(array_keys($this->form($page)->getFlatFields(withHidden: true)), $names));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function fieldsIn(Section $block, array $names): array
    {
        $fields = [];

        foreach ($block->getChildSchema()?->getFlatFields(withHidden: true) ?? [] as $name => $field) {
            $fields[] = (string) $name;
        }

        return array_values(array_intersect($fields, $names));
    }
}
