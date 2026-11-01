<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\GuideConditionEvaluatorInterface;
use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\Support\Knowledge\DeniedKnowledgeCapability;
use Tests\Support\Knowledge\FakeKnowledgeCapability;
use Tests\TestCase;

final class GuideMarkdownRendererTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GuideRendererInterface $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->renderer = $this->app->make(GuideRendererInterface::class);
    }

    private function guide(string $body, ?string $module = null): Guide
    {
        return new Guide(
            GuideId::fromString('core.example'),
            $module === null ? GuideSource::Core : GuideSource::Module,
            $module,
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

    private function html(string $body): string
    {
        return $this->renderer->toHtml($this->guide($body), UserModel::factory()->create());
    }

    public function test_raw_html_is_stripped(): void
    {
        $html = $this->html("Hola <script>alert(1)</script> <b>mundo</b>\n\n<div onclick=\"x()\">caja</div>");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<div', $html);
    }

    public function test_unsafe_links_are_neutralised(): void
    {
        $html = $this->html('[clic](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_callouts_render_as_asides(): void
    {
        $html = $this->html("> [!TIP]\n> Usa el buscador.\n\n> [!IMPORTANT]\n> Léelo.\n\n> [!WARNING]\n> Cuidado.\n\n> Cita normal.");

        $this->assertStringContainsString('<aside class="kb-callout kb-callout--tip" role="note">', $html);
        $this->assertStringContainsString('<aside class="kb-callout kb-callout--important" role="note">', $html);
        $this->assertStringContainsString('<aside class="kb-callout kb-callout--warning" role="note">', $html);
        $this->assertStringContainsString('Consejo', $html);
        $this->assertStringContainsString('Usa el buscador.', $html);
        $this->assertStringNotContainsString('[!TIP]', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertSame(1, substr_count($html, '<blockquote>'));
    }

    public function test_action_button_renders_for_existing_parameterless_route(): void
    {
        $html = $this->html('[[action:events.index|Ver los <eventos>]]');

        $this->assertStringContainsString('<a class="kb-action" href="', $html);
        $this->assertStringContainsString('/eventos"', $html);
        $this->assertStringContainsString('Ver los &lt;eventos&gt;</a>', $html);
    }

    public function test_action_button_is_omitted_for_missing_route(): void
    {
        $html = $this->html('Antes [[action:no.such.route|Ir]] después');

        $this->assertStringNotContainsString('kb-action', $html);
        $this->assertStringNotContainsString('Ir<', $html);
        $this->assertStringContainsString('Antes', $html);
    }

    public function test_action_button_is_omitted_for_route_with_required_parameters(): void
    {
        $html = $this->html('[[action:events.show|Ver]]');

        $this->assertStringNotContainsString('kb-action', $html);
    }

    public function test_action_button_with_optional_parameters_only_renders(): void
    {
        Route::get('/kb-test/{page?}', fn () => 'x')->name('kb.test.optional');
        Route::getRoutes()->refreshNameLookups();

        $this->assertStringContainsString('kb-action', $this->html('[[action:kb.test.optional|Ir]]'));
    }

    public function test_action_button_with_failing_if_condition_is_omitted(): void
    {
        $registry = $this->app->make(KnowledgeCapabilityRegistryInterface::class);
        $registry->register(FakeKnowledgeCapability::class);
        $registry->register(DeniedKnowledgeCapability::class);

        $this->assertStringContainsString('kb-action', $this->html('[[action:events.index|Ver|if=fake.can_do_it]]'));
        $this->assertStringNotContainsString('kb-action', $this->html('[[action:events.index|Ver|if=!fake.can_do_it]]'));
        $this->assertStringNotContainsString('kb-action', $this->html('[[action:events.index|Ver|if=unknown.cap]]'));
    }

    public function test_action_button_if_condition_can_be_a_permission(): void
    {
        $outsider = UserModel::factory()->create();
        $admin = UserModel::factory()->admin()->create();
        $guide = $this->guide('[[action:events.index|Ver|if=events.view_any]] [[action:events.index|Otro|if=!events.view_any]]');

        $forAdmin = $this->renderer->toHtml($guide, $admin);
        $forOutsider = $this->renderer->toHtml($guide, $outsider);

        $this->assertStringContainsString('>Ver</a>', $forAdmin);
        $this->assertStringNotContainsString('>Otro</a>', $forAdmin);
        $this->assertStringNotContainsString('>Ver</a>', $forOutsider);
        $this->assertStringContainsString('>Otro</a>', $forOutsider);
    }

    public function test_placeholders_are_replaced_and_escaped(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set('guild_name', '<b>Gremio</b>');

        $html = $this->html('Bienvenido a {{site_name}}. {{unknown_one}}');

        $this->assertStringContainsString('&lt;b&gt;Gremio&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Gremio', $html);
        $this->assertStringContainsString('{{unknown_one}}', $html);
    }

    public function test_association_role_placeholder_uses_the_role_display_name(): void
    {
        RoleModel::query()->where('name', 'socio')->update(['display_name' => 'Socia <i>de honor</i>']);

        $html = $this->html('Eres {{association_role}}.');

        $this->assertStringContainsString('Socia &lt;i&gt;de honor&lt;/i&gt;', $html);
    }

    public function test_association_role_placeholder_falls_back_when_the_role_is_missing(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set('association_member_role', 'rol-inexistente');

        $html = $this->html('Eres {{association_role}}.');

        $this->assertStringContainsString('Eres '.__('knowledge.placeholders.association_role_fallback').'.', $html);
    }

    public function test_plain_text_keeps_button_labels_and_resolved_placeholders(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set('guild_name', 'Gremio & Cía');

        $text = $this->renderer->toPlainText(
            "# Título\n\nBienvenido a {{site_name}}.\n\n> [!TIP]\n> Un consejo.\n\n[[action:events.index|Ver los eventos]]\n\n- uno\n- dos",
        );

        $this->assertStringContainsString('Bienvenido a Gremio & Cía.', $text);
        $this->assertStringContainsString('Un consejo.', $text);
        $this->assertStringContainsString('Ver los eventos', $text);
        $this->assertStringNotContainsString('[!TIP]', $text);
        $this->assertStringNotContainsString('Consejo', $text);
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringNotContainsString("\n", $text);
        $this->assertStringContainsString('Título Bienvenido', $text);
        $this->assertDoesNotMatchRegularExpression('/\s{2,}/', $text);
    }

    public function test_negated_permission_condition_fails_closed_for_a_disabled_module(): void
    {
        $outsider = UserModel::factory()->create();
        $resolver = Mockery::mock(EnabledModulesResolverInterface::class);
        $resolver->shouldReceive('names')->andReturn([]);
        $this->app->instance(EnabledModulesResolverInterface::class, $resolver);
        $this->app->forgetInstance(GuideConditionEvaluatorInterface::class);
        $renderer = $this->app->make(GuideRendererInterface::class);

        $html = $renderer->toHtml($this->guide('[[action:events.index|Ver|if=!events.view_any]]', 'some-module'), $outsider);

        $this->assertStringNotContainsString('kb-action', $html);
    }
}
