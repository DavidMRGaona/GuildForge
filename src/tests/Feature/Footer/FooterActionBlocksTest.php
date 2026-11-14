<?php

declare(strict_types=1);

namespace Tests\Feature\Footer;

use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class FooterActionBlocksTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    public function test_the_core_offers_the_new_blocks_for_the_footer_only(): void
    {
        $registry = app(SiteBlockRegistryInterface::class);

        foreach (['core.upcoming_events', 'core.button', 'core.logos'] as $key) {
            $this->assertContains($key, array_map(static fn ($block): string => $block->key(), $registry->forPlacement(SiteBlockPlacement::Footer)));
            $this->assertNotContains($key, array_map(static fn ($block): string => $block->key(), $registry->forPlacement(SiteBlockPlacement::Popup)));
        }
    }

    // ---- core.upcoming_events ----

    public function test_upcoming_events_are_the_next_published_ones_soonest_first(): void
    {
        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));

        $this->event('Tercero', '2026-06-20 18:00:00');
        $this->event('Primero', '2026-06-16 18:00:00', location: 'Local de la asociación');
        $this->event('Segundo', '2026-06-18 18:00:00');
        $this->event('Cuarto', '2026-06-25 18:00:00');
        $this->event('Borrador', '2026-06-17 18:00:00', published: false);
        $this->event('Ya pasó', '2026-06-10 18:00:00');

        $block = $this->resolve('core.upcoming_events', []);

        $this->assertSame('components/siteBlocks/UpcomingEventsBlock.vue', $block->component);
        // Three by default
        $this->assertSame(['Primero', 'Segundo', 'Tercero'], array_column($block->props['events'], 'title'));
        $this->assertSame('Local de la asociación', $block->props['events'][0]['location']);
        $this->assertNull($block->props['events'][1]['location'] ?? null);
        $this->assertSame('/eventos', $block->props['allUrl']);
    }

    public function test_upcoming_events_follow_the_chosen_number_within_one_to_five(): void
    {
        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));

        foreach (range(1, 7) as $day) {
            $this->event('Evento '.$day, sprintf('2026-06-%02d 18:00:00', 15 + $day));
        }

        $count = fn (mixed $limit): int => count($this->resolve('core.upcoming_events', ['limit' => $limit])->props['events']);

        $this->assertSame(1, $count(1));
        $this->assertSame(5, $count(5));
        $this->assertSame(5, $count('5'));
        $this->assertSame(5, $count(99));
        $this->assertSame(1, $count(0));
        $this->assertSame(3, $count('muchos'));
        $this->assertSame(3, $count(null));
    }

    public function test_today_and_the_dates_are_the_display_zones(): void
    {
        $this->useUtcStorage();
        // 00:30 on 16 June in the display zone (Madrid), still 15 June in UTC
        $this->travelTo(CarbonImmutable::parse('2026-06-15 22:30:00', 'UTC'));

        $this->event('Ayer en Madrid', '2026-06-15 21:59:00');
        $this->event('Hoy a primera hora', '2026-06-15 22:00:00');
        $this->event('Hoy por la tarde', '2026-06-16 16:00:00');

        $events = $this->resolve('core.upcoming_events', [])->props['events'];

        // Madrid's 16 June starts at 22:00 UTC: what started earlier that day still shows, the day before does not
        $this->assertSame(['Hoy a primera hora', 'Hoy por la tarde'], array_column($events, 'title'));
        $this->assertSame('2026-06-16T00:00:00+02:00', $events[0]['startDate']);
        $this->assertSame('2026-06-16T18:00:00+02:00', $events[1]['startDate']);
    }

    public function test_upcoming_events_link_to_their_page_and_hide_the_all_events_link_when_switched_off(): void
    {
        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
        $this->event('Noche de rol', '2026-06-16 18:00:00', slug: 'noche-de-rol');

        $block = $this->resolve('core.upcoming_events', ['show_all_link' => false]);

        $this->assertSame('/eventos/noche-de-rol', $block->props['events'][0]['url']);
        $this->assertNull($block->props['allUrl']);
    }

    public function test_upcoming_events_cost_one_query_whatever_the_number_of_events(): void
    {
        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
        foreach (range(1, 5) as $day) {
            $this->event('Evento '.$day, sprintf('2026-06-%02d 18:00:00', 15 + $day));
        }
        app(SiteBlockRendererInterface::class)->resolveBlock('core.upcoming_events', ['limit' => 5], SiteBlockPlacement::Footer, null);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $block = $this->resolve('core.upcoming_events', ['limit' => 5]);
        $queries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'events'));

        $this->assertCount(5, $block->props['events']);
        $this->assertCount(1, $queries);
    }

    public function test_the_block_is_the_same_for_everybody(): void
    {
        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
        $this->event('Noche de rol', '2026-06-16 18:00:00');
        $this->event('Borrador', '2026-06-17 18:00:00', published: false);

        $guest = $this->resolve('core.upcoming_events', [])->props;
        $member = $this->resolve('core.upcoming_events', [], UserModel::factory()->create())->props;

        $this->assertSame(['Noche de rol'], array_column($guest['events'], 'title'));
        $this->assertSame($guest, $member);
    }

    // ---- core.button ----

    public function test_button_draws_its_text_link_and_style(): void
    {
        $external = $this->resolve('core.button', ['label' => ' Únete ', 'url' => 'https://example.com/unete', 'style' => 'secondary']);
        $internal = $this->resolve('core.button', ['label' => 'Eventos', 'url' => '/eventos']);

        $this->assertSame('components/siteBlocks/ButtonBlock.vue', $external->component);
        $this->assertSame(['label' => 'Únete', 'url' => 'https://example.com/unete', 'external' => true, 'style' => 'secondary'], $external->props);
        $this->assertSame(['label' => 'Eventos', 'url' => '/eventos', 'external' => false, 'style' => 'primary'], $internal->props);
    }

    public function test_button_with_an_unknown_style_is_primary(): void
    {
        $this->assertSame('primary', $this->resolve('core.button', ['label' => 'A', 'url' => '/a', 'style' => 'huge'])->props['style']);
    }

    public function test_button_with_a_link_that_is_not_allowed_is_not_drawn(): void
    {
        Log::spy();

        foreach (['javascript:alert(1)', '//evil.example', '/\\evil.example', 'data:text/html,x', 'ftp://example.com', '', null, ['https://example.com']] as $url) {
            $this->assertNull($this->resolveOrNull('core.button', ['label' => 'Entrar', 'url' => $url]), var_export($url, true));
        }
    }

    public function test_button_without_text_is_not_drawn(): void
    {
        $this->assertNull($this->resolveOrNull('core.button', ['label' => '  ', 'url' => '/eventos']));
        $this->assertNull($this->resolveOrNull('core.button', ['url' => '/eventos']));
    }

    public function test_button_allows_http_only_in_local_and_testing(): void
    {
        // This suite runs in the testing environment
        $this->assertSame('http://localhost:8080/x', $this->resolve('core.button', ['label' => 'A', 'url' => 'http://localhost:8080/x'])->props['url']);

        $this->app['env'] = 'production';

        $this->assertNull($this->resolveOrNull('core.button', ['label' => 'A', 'url' => 'http://example.com']));
        $this->assertNotNull($this->resolveOrNull('core.button', ['label' => 'A', 'url' => 'https://example.com']));
    }

    // ---- core.logos ----

    public function test_logos_draw_each_image_with_its_text_and_optional_link(): void
    {
        $block = $this->resolve('core.logos', ['logos' => [
            ['image' => 'footer/logos/a.png', 'alt' => 'Federación', 'url' => 'https://example.com'],
            ['image' => 'footer/logos/b.png', 'alt' => 'Club', 'url' => ''],
            ['image' => 'events/c.png', 'alt' => 'Local', 'url' => '/nosotros'],
        ]]);

        $this->assertSame('components/siteBlocks/LogosBlock.vue', $block->component);
        $this->assertSame(['Federación', 'Club', 'Local'], array_column($block->props['logos'], 'alt'));
        $this->assertStringContainsString('footer/logos/a', $block->props['logos'][0]['src']);
        $this->assertSame('https://example.com', $block->props['logos'][0]['url']);
        $this->assertTrue($block->props['logos'][0]['external']);
        $this->assertNull($block->props['logos'][1]['url']);
        $this->assertFalse($block->props['logos'][1]['external']);
        $this->assertSame('/nosotros', $block->props['logos'][2]['url']);
        $this->assertFalse($block->props['logos'][2]['external']);
    }

    public function test_a_logo_without_alternative_text_or_image_is_left_out(): void
    {
        $block = $this->resolve('core.logos', ['logos' => [
            ['image' => 'footer/logos/a.png', 'alt' => '  '],
            ['image' => 'footer/logos/b.png'],
            ['alt' => 'Sin imagen'],
            ['image' => 'footer/logos/ok.png', 'alt' => 'Bien'],
        ]]);

        $this->assertSame(['Bien'], array_column($block->props['logos'], 'alt'));
    }

    public function test_a_logo_link_that_is_not_allowed_is_drawn_without_link(): void
    {
        $logo = $this->resolve('core.logos', ['logos' => [
            ['image' => 'footer/logos/a.png', 'alt' => 'Club', 'url' => 'javascript:alert(1)'],
        ]])->props['logos'][0];

        $this->assertNull($logo['url']);
        $this->assertFalse($logo['external']);
    }

    public function test_logos_without_settings_have_nothing_to_draw(): void
    {
        $this->assertSame([], $this->resolve('core.logos', [])->props['logos']);
        $this->assertSame([], $this->resolve('core.logos', ['logos' => 'x'])->props['logos']);
    }

    private function event(string $title, string $start, bool $published = true, ?string $location = null, ?string $slug = null): EventModel
    {
        $instant = CarbonImmutable::parse($start, 'UTC');

        return EventModel::factory()->create([
            'title' => $title,
            'slug' => $slug ?? Str::slug($title).'-'.Str::random(4),
            'is_published' => $published,
            'start_date' => $instant,
            'end_date' => $instant->addHours(3),
            'location' => $location,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolve(string $key, array $settings, ?UserModel $user = null): ResolvedSiteBlockDTO
    {
        $block = $this->resolveOrNull($key, $settings, $user);

        $this->assertInstanceOf(ResolvedSiteBlockDTO::class, $block);

        return $block;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveOrNull(string $key, array $settings, ?UserModel $user = null): ?ResolvedSiteBlockDTO
    {
        return app(SiteBlockRendererInterface::class)->resolveBlock($key, $settings, SiteBlockPlacement::Footer, $user);
    }
}
