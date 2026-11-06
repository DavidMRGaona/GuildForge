<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\DTOs\Response\KnowledgeGuideResponseDTO;
use App\Application\Knowledge\DTOs\SiteGuideDTO;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Application\Knowledge\Services\SiteGuideServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideAliasModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\Knowledge\FakeKnowledgeCapability;
use Tests\TestCase;

final class SiteGuideServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SiteGuideServiceInterface $service;

    /** @var list<string> */
    private array $refreshed = [];

    /** @var list<string> */
    private array $forgotten = [];

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->category('community', 'site');
        $this->category('content', 'panel');
        $this->spyOnIndexer();
        $this->service = $this->app->make(SiteGuideServiceInterface::class);
    }

    public function test_create_stores_a_published_site_guide_with_a_stable_id(): void
    {
        $id = $this->service->create($this->dto(
            title: 'Normas de la casa',
            keywords: ['normas', 'convivencia'],
            conditions: new GuideConditions(permissionsAny: ['events.create'], associationMember: true),
            featured: true,
        ));

        $this->assertSame('site.normas-de-la-casa', $id->value());
        $guide = KnowledgeGuideModel::query()->where('guide_id', $id->value())->sole();
        $this->assertSame('site', $guide->source);
        $this->assertNull($guide->module);
        $this->assertSame('es', $guide->locale);
        $this->assertSame('html', $guide->body_format);
        $this->assertSame('site', $guide->audience);
        $this->assertSame('community', $guide->category_id);
        $this->assertSame(['normas', 'convivencia'], $guide->keywords);
        $this->assertSame(['association_member' => true, 'permissions' => ['any' => ['events.create']]], $guide->conditions);
        $this->assertSame('Aquí jugamos con respeto.', $guide->body_text);
        $this->assertTrue($guide->featured);
        $this->assertTrue($guide->active);
        $this->assertNotNull($guide->first_seen_at);
        $this->assertNull($guide->content_changed_at);
        $this->assertSame(40, strlen($guide->content_hash));
        $this->assertSame([$id->value()], $this->refreshed);
    }

    public function test_ids_never_repeat_an_existing_guide_or_alias(): void
    {
        $first = $this->service->create($this->dto(title: 'Normas'));
        KnowledgeGuideAliasModel::query()->create(['alias_id' => 'site.normas-3', 'guide_id' => 'site.other']);

        $second = $this->service->create($this->dto(title: 'Normas'));
        $third = $this->service->create($this->dto(title: 'Normas'));
        $symbols = $this->service->create($this->dto(title: '¿¡!?'));
        $long = $this->service->create($this->dto(title: str_repeat('Partida larga ', 8)));

        $this->assertSame('site.normas', $first->value());
        $this->assertSame('site.normas-2', $second->value());
        $this->assertSame('site.normas-4', $third->value());
        $this->assertSame('site.guia', $symbols->value());
        $this->assertLessThanOrEqual(65, strlen($long->value()));
        $this->assertStringEndsNotWith('-', $long->value());
    }

    public function test_draft_gets_its_first_seen_date_when_published(): void
    {
        $id = $this->service->create($this->dto(published: false));
        $this->assertNull($this->model($id)->first_seen_at);
        $this->assertFalse($this->model($id)->active);

        $this->travelTo(CarbonImmutable::now()->addDay());
        $this->service->update($id, $this->dto(body: '<p>Otro texto.</p>'));

        $guide = $this->model($id);
        $this->assertTrue($guide->active);
        $this->assertNotNull($guide->first_seen_at);
        $this->assertNull($guide->content_changed_at);
    }

    public function test_update_keeps_the_id_and_marks_published_changes(): void
    {
        $id = $this->service->create($this->dto(title: 'Normas'));

        $this->service->update($id, $this->dto(title: 'Normas'));
        $this->assertNull($this->model($id)->content_changed_at);

        $this->service->update($id, $this->dto(title: 'Normas renovadas', audience: GuideAudience::Panel, categoryId: 'content'));

        $guide = $this->model($id);
        $this->assertSame('site.normas', $guide->guide_id);
        $this->assertSame('Normas renovadas', $guide->title);
        $this->assertSame('panel', $guide->audience);
        $this->assertNotNull($guide->content_changed_at);
        $this->assertSame(1, KnowledgeGuideModel::query()->count());
    }

    public function test_delete_removes_the_guide_its_metrics_and_index_entry(): void
    {
        $id = $this->service->create($this->dto());
        KnowledgeGuideOverrideModel::query()->create(['guide_id' => $id->value(), 'hidden' => true, 'featured' => null]);
        KnowledgeGuideAliasModel::query()->create(['alias_id' => 'site.old', 'guide_id' => $id->value()]);
        DB::table('knowledge_guide_feedback')->insert(['id' => (string) Str::uuid(), 'guide_id' => $id->value(), 'day' => '2026-11-01', 'helpful_count' => 2, 'not_helpful_count' => 0]);
        DB::table('knowledge_guide_feedback')->insert(['id' => (string) Str::uuid(), 'guide_id' => 'core.other', 'day' => '2026-11-01', 'helpful_count' => 1, 'not_helpful_count' => 0]);

        $this->service->delete($id);

        $this->assertSame(0, KnowledgeGuideModel::query()->count());
        $this->assertSame(0, KnowledgeGuideOverrideModel::query()->count());
        $this->assertSame(0, KnowledgeGuideAliasModel::query()->count());
        $this->assertSame(['core.other'], DB::table('knowledge_guide_feedback')->pluck('guide_id')->all());
        $this->assertSame([$id->value()], $this->forgotten);
    }

    public function test_official_guides_cannot_be_changed_or_deleted(): void
    {
        KnowledgeGuideModel::query()->create([
            'guide_id' => 'core.signup', 'locale' => 'es', 'source' => 'core', 'module' => null, 'audience' => 'site',
            'category_id' => 'community', 'sort_order' => 1, 'title' => 'Alta', 'summary' => 'S', 'keywords' => [],
            'body_format' => 'markdown', 'body' => 'x', 'body_text' => 'x', 'conditions' => [], 'related' => [],
            'featured' => false, 'content_hash' => str_repeat('a', 40), 'active' => true,
        ]);

        foreach ([
            fn () => $this->service->update(GuideId::fromString('core.signup'), $this->dto()),
            fn () => $this->service->delete(GuideId::fromString('core.signup')),
            fn () => $this->service->update(GuideId::fromString('site.missing'), $this->dto()),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected a SiteGuideException');
            } catch (SiteGuideException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Alta', KnowledgeGuideModel::query()->where('guide_id', 'core.signup')->value('title'));
    }

    public function test_category_must_exist_for_the_audience(): void
    {
        $this->expectException(SiteGuideException::class);

        $this->service->create($this->dto(audience: GuideAudience::Panel, categoryId: 'community'));
    }

    public function test_conditions_must_name_registered_permissions_and_capabilities(): void
    {
        $this->app->make(KnowledgeCapabilityRegistryInterface::class)->register(FakeKnowledgeCapability::class);
        $this->service->create($this->dto(conditions: new GuideConditions(capabilities: ['fake.can_do_it'], permissionsAll: ['events.create'])));

        foreach ([
            new GuideConditions(permissionsAll: ['no.such_permission']),
            new GuideConditions(permissionsAny: ['no.such_permission']),
            new GuideConditions(capabilities: ['no.such_capability']),
            new GuideConditions(negatedCapabilities: ['no.such_capability']),
            new GuideConditions(modules: ['some-module']),
        ] as $conditions) {
            try {
                $this->service->create($this->dto(conditions: $conditions));
                $this->fail('Expected a SiteGuideException');
            } catch (SiteGuideException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, KnowledgeGuideModel::query()->count());
    }

    public function test_related_guides_must_exist_and_not_be_the_guide_itself(): void
    {
        $id = $this->service->create($this->dto(title: 'Primera'));
        $this->service->create($this->dto(title: 'Segunda', related: [$id]));

        $this->expectException(SiteGuideException::class);
        $this->service->update($id, $this->dto(title: 'Primera', related: [$id]));
    }

    public function test_unknown_related_guide_is_rejected(): void
    {
        $this->expectException(SiteGuideException::class);

        $this->service->create($this->dto(related: [GuideId::fromString('core.missing')]));
    }

    public function test_title_longer_than_the_form_allows_is_rejected(): void
    {
        $this->expectException(SiteGuideException::class);

        $this->service->create($this->dto(title: str_repeat('a', 121)));
    }

    public function test_title_and_summary_are_required(): void
    {
        $this->expectException(SiteGuideException::class);

        $this->service->create($this->dto(title: '   '));
    }

    public function test_published_site_guides_reach_the_help_centre_and_drafts_do_not(): void
    {
        $published = $this->service->create($this->dto(
            title: 'Normas',
            body: '<p>Hola.</p><div data-type="customBlock" data-id="kb-action" data-config="{&quot;target&quot;:&quot;events.index&quot;,&quot;label&quot;:&quot;Ver eventos&quot;}"></div>',
        ));
        $draft = $this->service->create($this->dto(title: 'Borrador', published: false));
        $membersOnly = $this->service->create($this->dto(title: 'Solo socios', conditions: new GuideConditions(associationMember: true)));
        $user = UserModel::factory()->create();

        $query = $this->app->make(KnowledgeQueryServiceInterface::class);
        $visible = $query->visibleIds($user, GuideAudience::Site);

        $this->assertContains($published->value(), $visible);
        $this->assertNotContains($draft->value(), $visible);
        $this->assertNotContains($membersOnly->value(), $visible);
        $page = $query->guide($user, GuideAudience::Site, $published);
        $this->assertInstanceOf(KnowledgeGuideResponseDTO::class, $page);
        $this->assertStringContainsString('<a class="kb-action"', $page->html);
        $this->assertSame([$published->value()], array_map(
            static fn ($hit): string => $hit->id,
            $query->search($user, GuideAudience::Site, 'ver eventos', 5),
        ));
    }

    /**
     * @param  list<string>  $keywords
     * @param  list<GuideId>  $related
     */
    private function dto(
        string $title = 'Normas de la casa',
        string $body = '<p>Aquí jugamos con <strong>respeto</strong>.</p>',
        GuideAudience $audience = GuideAudience::Site,
        string $categoryId = 'community',
        array $keywords = [],
        GuideConditions $conditions = new GuideConditions,
        array $related = [],
        bool $featured = false,
        bool $published = true,
    ): SiteGuideDTO {
        return new SiteGuideDTO(
            title: $title,
            summary: 'Cómo convivimos en el local.',
            audience: $audience,
            categoryId: $categoryId,
            body: $body,
            keywords: $keywords,
            order: 50,
            conditions: $conditions,
            related: $related,
            featured: $featured,
            published: $published,
        );
    }

    private function model(GuideId $id): KnowledgeGuideModel
    {
        return KnowledgeGuideModel::query()->where('guide_id', $id->value())->sole();
    }

    private function category(string $id, string $audience): void
    {
        KnowledgeCategoryModel::query()->create(['category_id' => $id, 'module' => null, 'audience' => $audience, 'sort_order' => 1, 'icon' => 'heroicon-o-book-open', 'label_key' => 'knowledge.categories.'.$id]);
    }

    private function spyOnIndexer(): void
    {
        $indexer = Mockery::mock(KnowledgeIndexerInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refresh')->andReturnUsing(function (GuideId $id): void {
                $this->refreshed[] = $id->value();
            });
            $mock->shouldReceive('forget')->andReturnUsing(function (GuideId $id): void {
                $this->forgotten[] = $id->value();
            });
        });
        $this->app->instance(KnowledgeIndexerInterface::class, $indexer);
    }
}
