<?php

declare(strict_types=1);

namespace Tests\Feature\Footer;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Footer\Services\FooterRendererInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Footer\ValueObjects\FooterBlock;
use App\Domain\Footer\ValueObjects\FooterColumn;
use App\Domain\Footer\ValueObjects\FooterLayout;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

final class SharedFooterPropTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_without_a_layout_the_fixed_footer_is_kept(): void
    {
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer, 'label' => 'Contacto', 'url' => '/nosotros']);

        $this->get('/')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('footer', null)
            // The fixed footer still reads the footer menu, as before
            ->where('navigation.footer.0.label', 'Contacto'));
    }

    public function test_an_empty_layout_keeps_the_fixed_footer(): void
    {
        app(SettingsServiceInterface::class)->set('footer_layout', '{"version":1,"columns":[{"blocks":[]}]}');

        $this->get('/')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('footer', null));
    }

    public function test_a_layout_sends_its_resolved_columns_and_the_bottom_bar(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('guild_name', 'Asociación de ejemplo');
        $settings->set('legal_notice_published', '1');
        $settings->set('legal_privacy_published', '1');
        $settings->set('legal_terms_published', '0');
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer2, 'label' => 'Discord', 'url' => 'https://discord.gg/x']);

        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([new FooterBlock('core.links', 'Comunidad', ['menu' => 'footer_2'])]),
            // A block nobody registers (its module is disabled) is left out, and so is the column it leaves empty
            new FooterColumn([new FooterBlock('disabled-module.block', 'Nada', [])]),
            new FooterColumn([new FooterBlock('core.text', null, ['content' => '<p>Hola</p>'])]),
        ]));

        $this->get('/')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('footer.columns', 2)
            ->where('footer.columns.0.blocks.0.title', 'Comunidad')
            ->where('footer.columns.0.blocks.0.block.key', 'core.links')
            ->where('footer.columns.0.blocks.0.block.module', null)
            ->where('footer.columns.0.blocks.0.block.component', 'components/siteBlocks/LinksBlock.vue')
            ->where('footer.columns.0.blocks.0.block.props.items.0.label', 'Discord')
            ->where('footer.columns.1.blocks.0.title', null)
            ->where('footer.columns.1.blocks.0.block.props.html', '<p>Hola</p>')
            ->where('footer.siteName', 'Asociación de ejemplo')
            ->where('footer.legalLinks', [
                ['label' => 'Política de privacidad', 'href' => '/politica-de-privacidad'],
                ['label' => 'Aviso legal', 'href' => '/aviso-legal'],
            ]));
    }

    public function test_saving_another_layout_shows_on_the_next_page_view(): void
    {
        $service = app(FooterLayoutServiceInterface::class);
        $service->save(FooterLayout::of([new FooterColumn([new FooterBlock('core.text', 'Uno', [])])]));
        $this->get('/')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('footer.columns.0.blocks.0.title', 'Uno'));

        $this->app->forgetScopedInstances();
        $service->save(FooterLayout::of([new FooterColumn([new FooterBlock('core.text', 'Dos', [])])]));

        $this->get('/')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('footer.columns.0.blocks.0.title', 'Dos'));
    }

    public function test_a_failing_footer_falls_back_to_the_fixed_one(): void
    {
        $this->app->instance(FooterLayoutServiceInterface::class, new class implements FooterLayoutServiceInterface
        {
            public function layout(): FooterLayout
            {
                throw new RuntimeException('Base de datos caída');
            }

            public function save(FooterLayout $layout): void {}
        });
        $this->app->forgetInstance(FooterRendererInterface::class);

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('footer', null));
    }
}
