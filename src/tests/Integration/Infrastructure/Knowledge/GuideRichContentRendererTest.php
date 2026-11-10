<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Knowledge\DeniedKnowledgeCapability;
use Tests\Support\Knowledge\FakeKnowledgeCapability;
use Tests\TestCase;

/**
 * Bodies of the site's own guides: HTML from the panel's rich editor, with action buttons and markers.
 */
final class GuideRichContentRendererTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GuideRendererInterface $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->renderer = $this->app->make(GuideRendererInterface::class);
    }

    public function test_unsafe_markup_is_removed(): void
    {
        $html = $this->html('<p>Hola <a href="javascript:alert(1)">enlace</a><script>alert(1)</script></p><p onclick="x()">caja</p>');

        $this->assertStringContainsString('Hola', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_markers_are_replaced_and_escaped(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set('guild_name', 'Club <b>Dados</b>');

        $html = $this->html('<p>Bienvenido a '.$this->marker('site_name').'.</p>');

        $this->assertStringContainsString('Club &lt;b&gt;Dados&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Dados', $html);
    }

    public function test_action_button_renders_as_a_link(): void
    {
        $html = $this->html($this->button(['target' => 'events.index', 'label' => 'Ver <eventos>']));

        $this->assertStringContainsString('<a class="kb-action" href="'.route('events.index', [], false).'">Ver &lt;eventos&gt;</a>', $html);
    }

    public function test_action_button_carries_its_query_parameters(): void
    {
        $html = $this->html($this->button(['target' => 'profile.show?tab=surveys', 'label' => 'Mis encuestas']));

        // The sanitizer may write the query's "=" as an entity: the browser reads the same URL
        $this->assertStringContainsString('href="'.route('profile.show', ['tab' => 'surveys'], false).'"', html_entity_decode($html));
    }

    public function test_action_button_is_omitted_when_it_cannot_be_resolved(): void
    {
        $this->assertStringNotContainsString('kb-action', $this->html($this->button(['target' => 'no.such.route', 'label' => 'Ir'])));
        $this->assertStringNotContainsString('kb-action', $this->html($this->button(['target' => 'events.show', 'label' => 'Ir'])));
        $this->assertStringNotContainsString('kb-action', $this->html($this->button(['label' => 'Ir'])));
        $this->assertStringNotContainsString('kb-action', $this->html($this->button(['target' => 'events.index', 'label' => ''])));
        $this->assertStringNotContainsString('kb-action', $this->html($this->button(['target' => ['events.index'], 'label' => 'Ir'])));
    }

    public function test_action_button_condition_hides_it(): void
    {
        $registry = $this->app->make(KnowledgeCapabilityRegistryInterface::class);
        $registry->register(FakeKnowledgeCapability::class);
        $registry->register(DeniedKnowledgeCapability::class);

        $this->assertStringContainsString('kb-action', $this->html($this->button(['target' => 'events.index', 'label' => 'Ver', 'condition' => 'fake.can_do_it'])));
        $this->assertStringNotContainsString('kb-action', $this->html($this->button(['target' => 'events.index', 'label' => 'Ver', 'condition' => '!fake.can_do_it'])));
        $this->assertStringNotContainsString('kb-action', $this->html($this->button(['target' => 'events.index', 'label' => 'Ver', 'condition' => 'fake.can_do_it', 'negate' => true])));
        $this->assertStringContainsString('kb-action', $this->html($this->button(['target' => 'events.index', 'label' => 'Ver', 'condition' => 'events.create', 'negate' => true])));
        $this->assertStringNotContainsString('kb-action', $this->html($this->button(['target' => 'events.index', 'label' => 'Ver', 'condition' => 'events.create'])));
    }

    public function test_an_editor_image_shows_its_cloudinary_url(): void
    {
        $path = 'knowledge/content/2026/11/0b7e7c1e-6a0f-4c56-9d0e-1b2f3a4c5d6e.png';

        $html = $this->html('<p><img src="https://example.org/stale.png" data-id="'.$path.'" alt="Plano"></p>');

        // The sanitizer may write the query's "=" as an entity: the browser reads the same URL
        $this->assertStringContainsString('src="'.Storage::disk('images')->url($path).'"', html_entity_decode($html));
        $this->assertStringNotContainsString('stale.png', $html);
    }

    public function test_an_image_still_on_the_local_disk_keeps_showing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('3fJk2Lm9Qx.png', 'png');

        $html = $this->html('<p><img src="/storage/3fJk2Lm9Qx.png" data-id="3fJk2Lm9Qx.png" alt="Plano"></p>');

        $this->assertStringContainsString('src="'.Storage::disk('public')->url('3fJk2Lm9Qx.png').'"', $html);
    }

    public function test_plain_text_keeps_button_labels_and_resolved_markers(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set('guild_name', 'Club & Dados');

        $text = $this->renderer->toPlainText(
            '<h2>Normas</h2><p>En <strong>'.$this->marker('site_name').'</strong> jugamos.</p>'
            .$this->button(['target' => 'events.index', 'label' => 'Ver eventos'])
            .'<ul><li><p>Uno</p></li><li><p>Dos</p></li></ul>',
            GuideBodyFormat::Html,
        );

        $this->assertSame('Normas En Club & Dados jugamos. Ver eventos Uno Dos', $text);
    }

    private function html(string $body): string
    {
        $guide = new Guide(
            GuideId::fromString('site.house-rules'),
            GuideSource::Site,
            null,
            'es',
            GuideAudience::Site,
            'community',
            1,
            'Normas',
            'Resumen',
            [],
            GuideBodyFormat::Html,
            $body,
            '',
            new GuideConditions,
            [],
            false,
            false,
            null,
            null,
        );

        return $this->renderer->toHtml($guide, UserModel::factory()->create());
    }

    private function marker(string $name): string
    {
        return '<span data-type="mergeTag" data-id="'.$name.'"></span>';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function button(array $config): string
    {
        return '<div data-type="customBlock" data-id="kb-action" data-config="'.htmlspecialchars((string) json_encode($config), ENT_QUOTES).'"></div>';
    }
}
