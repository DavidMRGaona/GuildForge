<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Knowledge\DTOs\KnowledgeLintIssueDTO;
use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Domain\Modules\Entities\Module;
use App\Infrastructure\Knowledge\Lint\KnowledgeLinter;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Knowledge\FakeKnowledgeCapability;
use Tests\Support\Knowledge\KnowledgeFixtures;
use Tests\TestCase;

final class KnowledgeLinterTest extends TestCase
{
    use KnowledgeFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKnowledgeSandbox();

        Route::get('/lint-plain', static fn (): string => 'ok')->name('lint.plain');
        Route::get('/lint-param/{thing}', static fn (): string => 'ok')->name('lint.param');
        Route::get('/lint-optional/{thing?}', static fn (): string => 'ok')->name('lint.optional');
        $routes = app('router')->getRoutes();
        $routes->refreshNameLookups();

        $this->app->make(KnowledgeCapabilityRegistryInterface::class)->register(FakeKnowledgeCapability::class);
        $this->app->make(PermissionRegistryInterface::class)
            ->register(new PermissionDefinitionDTO('events.view_any', 'Ver eventos', 'events', 'view_any'));
    }

    protected function tearDown(): void
    {
        $this->tearDownKnowledgeSandbox();
        parent::tearDown();
    }

    /**
     * @return list<KnowledgeLintIssueDTO>
     */
    private function lint(?string $module = null): array
    {
        $modules = $this->createStub(ModuleManagerServiceInterface::class);
        $modules->method('findByName')->willReturnCallback(
            fn (string $name): ?Module => collect($this->knowledgeModules)
                ->first(static fn (Module $m): bool => $m->name()->value === $name),
        );

        $linter = $this->app->make(KnowledgeLinter::class, [
            'corePath' => $this->corePath(),
            'modules' => $modules,
        ]);

        return $linter->lint($module);
    }

    /**
     * @param  list<KnowledgeLintIssueDTO>  $issues
     */
    private function assertIssue(array $issues, string $fragment): void
    {
        foreach ($issues as $issue) {
            if (str_contains($issue->message, $fragment)) {
                $this->assertNotSame('', $issue->file);

                return;
            }
        }

        $this->fail(sprintf("No issue mentions '%s'. Got: %s", $fragment, json_encode(array_map(static fn (KnowledgeLintIssueDTO $i): string => $i->message, $issues))));
    }

    public function test_valid_fixture_has_no_issues(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: "Texto con {{site_name}} y {{association_role}}.\n\n[[action:lint.plain|Ir]]\n\n> [!TIP]\n> Un consejo.\n\n`<div>` en código.\n\n```html\n<p>hola</p>\n```", extra: "related: [core.two]\nprevious_ids: [core.old-one]\nconditions:\n  capabilities: [fake.can_do_it]\n  permissions:\n    all: [events.view_any]\n");
        $this->writeCoreGuide('b.md', 'core.two');

        $this->assertSame([], $this->lint());
    }

    public function test_invalid_document_is_reported_with_its_file(): void
    {
        $this->writeFile($this->corePath().'/es/broken.md', "sin cabecera\n");

        $issues = $this->lint();

        $this->assertCount(1, $issues);
        $this->assertStringEndsWith('broken.md', $issues[0]->file);
        $this->assertStringContainsString('front matter', $issues[0]->message);
    }

    public function test_core_guide_id_must_start_with_core(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');

        $this->assertIssue($this->lint(), "prefix 'account'");
    }

    public function test_module_guide_id_must_start_with_the_module_name(): void
    {
        $this->writeModuleGuide('demo', 'a.md', 'core.one');

        $this->assertIssue($this->lint('demo'), "prefix 'core'");
    }

    public function test_duplicate_guide_id_in_the_same_locale_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one');
        $this->writeCoreGuide('b.md', 'core.one');

        $this->assertIssue($this->lint(), 'already defined');
    }

    public function test_same_guide_id_in_other_locale_is_fine(): void
    {
        $this->writeCoreGuide('a.md', 'core.one');
        $this->writeCoreGuide('a.md', 'core.one', locale: 'en');

        $this->assertSame([], $this->lint());
    }

    public function test_duplicate_previous_ids_within_a_guide_are_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', extra: "previous_ids: [core.old, core.old]\n");

        $this->assertIssue($this->lint(), 'duplicate previous_ids');
    }

    public function test_previous_id_used_by_two_guides_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', extra: "previous_ids: [core.old]\n");
        $this->writeCoreGuide('b.md', 'core.two', extra: "previous_ids: [core.old]\n");

        $this->assertIssue($this->lint(), 'previous id core.old is already claimed');
    }

    public function test_previous_id_equal_to_an_existing_guide_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', extra: "previous_ids: [core.two]\n");
        $this->writeCoreGuide('b.md', 'core.two');

        $this->assertIssue($this->lint(), 'previous id core.two is the id of an existing guide');
    }

    public function test_unknown_category_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one');
        $this->writeFile($this->corePath().'/es/a.md', str_replace('category: getting-started', 'category: nope', $this->guideDocument('core.one', 'T', 'x')));

        $this->assertIssue($this->lint(), "category 'nope'");
    }

    public function test_category_audience_must_match_the_guide(): void
    {
        $this->writeCoreGuide('a.md', 'core.one');
        $this->writeFile($this->corePath().'/es/a.md', str_replace('audience: site', 'audience: panel', $this->guideDocument('core.one', 'T', 'x')));

        $this->assertIssue($this->lint(), 'audience');
    }

    public function test_module_guide_may_use_its_own_category(): void
    {
        $this->writeModuleCategories('demo', "<?php return [['id' => 'demo-cat', 'audience' => 'site', 'order' => 10, 'icon' => 'heroicon-o-star', 'label_key' => 'demo::knowledge.cat']];");
        $this->writeFile(
            $this->knowledgeSandbox.'/modules/demo/resources/knowledge/es/a.md',
            str_replace('category: getting-started', 'category: demo-cat', $this->guideDocument('demo.one', 'T', 'x')),
        );

        $this->assertSame([], $this->lint('demo'));
    }

    public function test_unknown_permission_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', extra: "conditions:\n  permissions:\n    any: [nothing.here]\n");

        $this->assertIssue($this->lint(), "permission 'nothing.here'");
    }

    public function test_unknown_capability_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', extra: "conditions:\n  capabilities: ['!ghost.cap']\n");

        $this->assertIssue($this->lint(), "capability 'ghost.cap'");
    }

    public function test_module_guide_may_use_core_and_its_own_capabilities_and_module(): void
    {
        $this->writeModuleGuide('fake', 'a.md', 'fake.one', body: '[[action:lint.plain|A|if=!fake.can_do_it]]', extra: "conditions:\n  capabilities: [fake.can_do_it, '!core.email_verification_required']\n  modules: [fake]\n");

        $this->assertSame([], $this->lint('fake'));
    }

    public function test_module_guide_must_not_use_another_modules_capability(): void
    {
        $this->writeModuleGuide('demo', 'a.md', 'demo.one', extra: "conditions:\n  capabilities: ['!fake.can_do_it']\n");

        $this->assertIssue($this->lint('demo'), "capability 'fake.can_do_it' belongs to another module");
    }

    public function test_module_button_must_not_use_another_modules_capability(): void
    {
        $this->writeModuleGuide('demo', 'a.md', 'demo.one', body: '[[action:lint.plain|A|if=fake.can_do_it]]');

        $this->assertIssue($this->lint('demo'), "capability 'fake.can_do_it' belongs to another module");
    }

    public function test_module_guide_must_not_require_another_module(): void
    {
        $this->writeModuleGuide('demo', 'a.md', 'demo.one', extra: "conditions:\n  modules: [event-registrations]\n");

        $this->assertIssue($this->lint('demo'), "module 'event-registrations' is another module");
    }

    public function test_core_guides_may_use_module_capabilities_and_modules(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: '[[action:lint.plain|A|if=fake.can_do_it]]', extra: "conditions:\n  capabilities: [fake.can_do_it]\n  modules: [fake]\n");

        $this->assertSame([], $this->lint());
    }

    public function test_unknown_button_route_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: '[[action:no.such.route|Ir]]');

        $this->assertIssue($this->lint(), "route 'no.such.route'");
    }

    public function test_button_route_with_required_parameters_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: '[[action:lint.param|Ir]]');

        $this->assertIssue($this->lint(), 'required parameters');
    }

    public function test_button_route_with_optional_parameter_is_fine(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: '[[action:lint.optional|Ir]]');

        $this->assertSame([], $this->lint());
    }

    public function test_malformed_button_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: '[[action:lint.plain]]');

        $this->assertIssue($this->lint(), 'malformed action button');
    }

    public function test_button_condition_accepts_permissions_capabilities_and_negation(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: "[[action:lint.plain|A|if=events.view_any]]\n\n[[action:lint.plain|B|if=!fake.can_do_it]]\n\n[[action:lint.plain|C|if=fake.can_do_it]]");

        $this->assertSame([], $this->lint());
    }

    public function test_button_condition_with_unknown_key_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: '[[action:lint.plain|A|if=!ghost.key]]');

        $this->assertIssue($this->lint(), "condition 'ghost.key'");
    }

    public function test_related_must_exist(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', extra: "related: [core.missing]\n");

        $this->assertIssue($this->lint(), 'related guide core.missing');
    }

    public function test_related_must_not_point_to_another_module(): void
    {
        $this->writeModuleGuide('other', 'o.md', 'other.guide');
        $this->writeModuleGuide('demo', 'a.md', 'demo.one', extra: "related: [other.guide]\n");

        $this->assertIssue($this->lint('demo'), 'related guide other.guide');
    }

    public function test_module_guide_may_relate_to_core_guides(): void
    {
        $this->writeCoreGuide('c.md', 'core.one');
        $this->writeModuleGuide('demo', 'a.md', 'demo.one', extra: "related: [core.one]\n");

        $this->assertSame([], $this->lint('demo'));
    }

    public function test_related_must_not_contain_the_guide_itself(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', extra: "related: [core.one]\n");

        $this->assertIssue($this->lint(), 'itself');
    }

    public function test_related_must_not_contain_duplicates(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', extra: "related: [core.two, core.two]\n");
        $this->writeCoreGuide('b.md', 'core.two');

        $this->assertIssue($this->lint(), 'duplicate related');
    }

    public function test_unknown_marker_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: 'Hola {{nombre}} y {{ site_name }}.');

        $issues = $this->lint();

        $this->assertIssue($issues, "marker '{{nombre}}'");
        $this->assertIssue($issues, "marker '{{ site_name }}'");
    }

    public function test_marker_inside_code_is_ignored(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: "Usa `{{nombre}}`.\n\n```\n{{otro}}\n```");

        $this->assertSame([], $this->lint());
    }

    public function test_raw_html_is_reported(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: 'Texto <b>fuerte</b>.');

        $this->assertIssue($this->lint(), 'raw HTML');
    }

    public function test_closing_tag_is_raw_html(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: 'Texto </span>.');

        $this->assertIssue($this->lint(), 'raw HTML');
    }

    public function test_less_than_before_a_space_or_digit_is_not_html(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: 'Si 3 < 5 o a <5 entonces sí.');

        $this->assertSame([], $this->lint());
    }

    public function test_invalid_locale_directory_is_reported(): void
    {
        $this->writeFile($this->corePath().'/spanish/a.md', $this->guideDocument('core.one', 'T', 'x'));
        $this->writeCoreGuide('b.md', 'core.two', locale: 'pt_BR');

        $issues = $this->lint();

        $this->assertIssue($issues, "locale directory 'spanish'");
        $this->assertCount(1, $issues);
    }

    public function test_unknown_module_is_reported(): void
    {
        $this->assertIssue($this->lint('ghost'), "module 'ghost'");
    }

    public function test_core_issues_are_not_mixed_with_module_runs_but_both_are_checked(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->writeModuleGuide('demo', 'a.md', 'demo.one');

        $this->assertIssue($this->lint('demo'), "prefix 'account'");
        $this->assertIssue($this->lint(), "prefix 'account'");
    }

    public function test_command_fails_with_issues_and_succeeds_without(): void
    {
        $this->app->instance(KnowledgeLinter::class, $this->app->make(KnowledgeLinter::class, ['corePath' => $this->corePath()]));
        $this->writeCoreGuide('a.md', 'core.one');

        $this->artisanCommand('knowledge:lint')->assertSuccessful();

        $this->writeCoreGuide('b.md', 'account.two');
        $this->artisanCommand('knowledge:lint')->assertFailed();
    }

    public function test_autolinks_are_not_raw_html(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: 'Visita <https://example.com/a?b=1> o escribe a <alguien@example.com>.');

        $this->assertSame([], $this->lint());
    }

    public function test_autolink_does_not_hide_real_html(): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: 'Visita <https://example.com> y <b>esto</b>.');

        $this->assertIssue($this->lint(), 'raw HTML');
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function buttonSamples(): array
    {
        return [
            'plain' => ['[[action:lint.plain|Ir]]', true],
            'spaces around the route' => ['[[action: lint.plain |Ir]]', true],
            'optional parameter and capability' => ['[[action:lint.optional|Ir|if=fake.can_do_it]]', true],
            'spaces around the condition' => ['[[action:lint.plain|Ir|if= fake.can_do_it ]]', true],
            'missing route' => ['[[action:no.such.route|Ir]]', false],
            'required parameter' => ['[[action:lint.param|Ir]]', false],
            'unknown condition' => ['[[action:lint.plain|Ir|if=ghost.key]]', false],
            'empty negated condition' => ['[[action:lint.plain|Ir|if=!]]', false],
            'missing label' => ['[[action:lint.plain]]', false],
        ];
    }

    #[DataProvider('buttonSamples')]
    public function test_linter_and_renderer_agree_on_buttons(string $button, bool $valid): void
    {
        $this->writeCoreGuide('a.md', 'core.one', body: $button);
        $linterAccepts = $this->lint() === [];

        $html = $this->app->make(GuideRendererInterface::class)->toHtml($this->parsedAsGuide($button), UserModel::factory()->create());
        $rendererAccepts = str_contains($html, 'kb-action');

        $this->assertSame($valid, $linterAccepts, 'linter verdict');
        $this->assertSame($valid, $rendererAccepts, 'renderer verdict');
    }

    private function parsedAsGuide(string $body): Guide
    {
        return new Guide(
            GuideId::fromString('core.one'),
            GuideSource::Core,
            null,
            'es',
            GuideAudience::Site,
            'getting-started',
            1,
            'Título',
            'Resumen',
            [],
            GuideBodyFormat::Markdown,
            $body,
            $body,
            new GuideConditions,
            [],
            false,
            false,
            null,
            null,
        );
    }
}
