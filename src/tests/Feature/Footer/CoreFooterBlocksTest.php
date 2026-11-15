<?php

declare(strict_types=1);

namespace Tests\Feature\Footer;

use App\Application\Content\Services\RichTextSanitizerInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class CoreFooterBlocksTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_core_offers_its_footer_blocks(): void
    {
        $keys = array_map(
            static fn ($block): string => $block->key(),
            app(SiteBlockRegistryInterface::class)->forPlacement(SiteBlockPlacement::Footer),
        );

        $this->assertSame(['core.links', 'core.text', 'core.brand', 'core.contact'], array_slice($keys, 0, 4));
        // Their settings belong to the footer's own JSON: none of them is offered for popups or the link page
        foreach ([SiteBlockPlacement::Popup, SiteBlockPlacement::LinkPage] as $placement) {
            $this->assertSame([], array_values(array_filter(
                array_map(static fn ($block): string => $block->key(), app(SiteBlockRegistryInterface::class)->forPlacement($placement)),
                static fn (string $key): bool => str_starts_with($key, 'core.'),
            )));
        }
    }

    public function test_links_draw_the_chosen_footer_menu_for_that_person(): void
    {
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer2, 'label' => 'Discord', 'url' => 'https://discord.gg/x', 'sort_order' => 1]);
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer2, 'label' => 'Mi perfil', 'url' => '/perfil', 'sort_order' => 2, 'visibility' => MenuVisibility::Authenticated]);
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer, 'label' => 'Otro menú', 'url' => '/otro']);

        $guest = $this->resolve('core.links', ['menu' => 'footer_2']);
        $member = $this->resolve('core.links', ['menu' => 'footer_2'], UserModel::factory()->create());

        $this->assertSame('components/siteBlocks/LinksBlock.vue', $guest->component);
        $this->assertSame(['Discord'], array_column($guest->props['items'], 'label'));
        $this->assertSame(['Discord', 'Mi perfil'], array_column($member->props['items'], 'label'));
    }

    public function test_links_with_nothing_visible_for_that_person_are_not_drawn(): void
    {
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer2, 'label' => 'Inactivo', 'url' => '/i', 'is_active' => false]);
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer3, 'label' => 'Mi perfil', 'url' => '/perfil', 'visibility' => MenuVisibility::Authenticated]);

        $renderer = app(SiteBlockRendererInterface::class);
        $user = UserModel::factory()->create();

        // Empty menu, only inactive links, and links of another audience: no block at all (title included)
        $this->assertNull($renderer->resolveBlock('core.links', ['menu' => 'footer_4'], SiteBlockPlacement::Footer, null));
        $this->assertNull($renderer->resolveBlock('core.links', ['menu' => 'footer_2'], SiteBlockPlacement::Footer, null));
        $this->assertNull($renderer->resolveBlock('core.links', ['menu' => 'footer_3'], SiteBlockPlacement::Footer, null));
        $this->assertNotNull($renderer->resolveBlock('core.links', ['menu' => 'footer_3'], SiteBlockPlacement::Footer, $user));
    }

    public function test_links_fall_back_to_the_first_footer_menu(): void
    {
        MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Cabecera', 'url' => '/c']);
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer, 'label' => 'Pie', 'url' => '/p']);

        foreach ([[], ['menu' => 'header'], ['menu' => 'otro'], ['menu' => ['footer_2']]] as $settings) {
            $this->assertSame(['Pie'], array_column($this->resolve('core.links', $settings)->props['items'], 'label'));
        }
    }

    public function test_text_is_sanitized(): void
    {
        $block = $this->resolve('core.text', ['content' => '<p>Hola <strong>mundo</strong></p><script>alert(1)</script><img src="https://example.com/x.png">']);

        $this->assertSame('components/siteBlocks/TextBlock.vue', $block->component);
        $this->assertStringContainsString('<strong>mundo</strong>', $block->props['html']);
        $this->assertStringNotContainsString('script', $block->props['html']);
        // Only the footer's own editor images are kept
        $this->assertStringNotContainsString('example.com', $block->props['html']);
    }

    public function test_text_is_sanitized_once_per_content(): void
    {
        $calls = 0;
        $this->app->instance(RichTextSanitizerInterface::class, new class($calls) implements RichTextSanitizerInterface
        {
            public function __construct(private int &$calls) {}

            public function sanitize(string $html, EditorImageOrigin $origin): string
            {
                $this->calls++;

                return 'limpio:'.$html;
            }
        });
        $this->app->forgetScopedInstances();

        $first = $this->resolve('core.text', ['content' => '<p>Uno</p>']);
        $this->resolve('core.text', ['content' => '<p>Uno</p>']);
        $other = $this->resolve('core.text', ['content' => '<p>Dos</p>']);

        $this->assertSame('limpio:<p>Uno</p>', $first->props['html']);
        $this->assertSame('limpio:<p>Dos</p>', $other->props['html']);
        $this->assertSame(2, $calls);
    }

    public function test_text_without_content_is_empty(): void
    {
        $this->assertSame('', $this->resolve('core.text', ['content' => ['no']])->props['html']);
    }

    public function test_brand_shows_name_logo_and_networks(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('guild_name', 'Asociación de ejemplo');
        $settings->set('social_discord', 'discord.gg/ejemplo');

        $block = $this->resolve('core.brand', ['description' => 'Juegos de mesa y rol']);

        $this->assertSame('components/siteBlocks/BrandBlock.vue', $block->component);
        $this->assertSame('Asociación de ejemplo', $block->props['name']);
        $this->assertSame('/images/defaults/logo-dark.png', $block->props['logo']);
        $this->assertSame('Juegos de mesa y rol', $block->props['description']);
        $this->assertSame('https://discord.gg/ejemplo', $block->props['socialLinks']['discord']);
        $this->assertArrayNotHasKey('facebook', $block->props['socialLinks']);
    }

    public function test_brand_hides_what_is_switched_off(): void
    {
        $block = $this->resolve('core.brand', ['show_logo' => false, 'show_name' => false, 'show_social' => false]);

        $this->assertNull($block->props['logo']);
        $this->assertNull($block->props['name']);
        $this->assertNull($block->props['description']);
        $this->assertSame([], $block->props['socialLinks']);
    }

    public function test_contact_shows_the_contact_settings(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('contact_email', 'hola@example.com');
        $settings->set('contact_phone', '600 000 000');
        $settings->set('contact_address', "Calle Mayor 1\n28001 Madrid");

        $block = $this->resolve('core.contact', ['show_phone' => false]);

        $this->assertSame('components/siteBlocks/ContactBlock.vue', $block->component);
        $this->assertSame('hola@example.com', $block->props['email']);
        $this->assertNull($block->props['phone']);
        $this->assertSame("Calle Mayor 1\n28001 Madrid", $block->props['address']);
        $this->assertSame('/nosotros#contact-section', $block->props['contactUrl']);
    }

    public function test_contact_without_settings_only_links_to_the_form(): void
    {
        $block = $this->resolve('core.contact', ['show_link' => true]);

        $this->assertNull($block->props['email']);
        $this->assertNull($block->props['address']);
        $this->assertSame('/nosotros#contact-section', $block->props['contactUrl']);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolve(string $key, array $settings, ?UserModel $user = null): ResolvedSiteBlockDTO
    {
        $block = app(SiteBlockRendererInterface::class)->resolveBlock($key, $settings, SiteBlockPlacement::Footer, $user);

        $this->assertInstanceOf(ResolvedSiteBlockDTO::class, $block);

        return $block;
    }
}
