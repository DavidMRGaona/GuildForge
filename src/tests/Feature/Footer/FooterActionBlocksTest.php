<?php

declare(strict_types=1);

namespace Tests\Feature\Footer;

use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Filament\SiteBlocks\UpcomingEventsFooterBlock;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: bool}>
     */
    public static function storageZones(): array
    {
        return ['UTC storage' => [true], 'Madrid storage' => [false]];
    }

    private function storeAs(bool $utc): void
    {
        if ($utc) {
            $this->useUtcStorage();
        }
    }

    #[DataProvider('storageZones')]
    public function test_upcoming_events_are_the_next_published_ones_soonest_first(bool $utc): void
    {
        $this->storeAs($utc);
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
        $this->assertNull($block->props['events'][1]['location']);
        $this->assertSame('/eventos', $block->props['allUrl']);
    }

    #[DataProvider('storageZones')]
    public function test_an_event_under_way_counts_and_one_that_ended_does_not(bool $utc): void
    {
        $this->storeAs($utc);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));

        $this->event('Terminó hace un minuto', '2026-06-15 06:59:00', end: '2026-06-15 09:59:00');
        $this->event('Termina ahora', '2026-06-15 07:00:00', end: '2026-06-15 10:00:00');
        $this->event('Jornada de varios días', '2026-06-13 08:00:00', end: '2026-06-16 20:00:00');
        $this->event('Mañana', '2026-06-16 18:00:00');

        $titles = array_column($this->resolve('core.upcoming_events', [])->props['events'], 'title');

        // Soonest start first: the multi-day one began before
        $this->assertSame(['Jornada de varios días', 'Termina ahora', 'Mañana'], $titles);
    }

    #[DataProvider('storageZones')]
    public function test_the_dates_are_the_display_zones_around_the_clock_change(bool $utc): void
    {
        $this->storeAs($utc);
        // Madrid goes back from 03:00 to 02:00 on 25 October 2026 (01:00 UTC): it is 02:30 summer time
        $this->travelTo(CarbonImmutable::parse('2026-10-25 00:30:00', 'UTC'));

        $this->event('Ya terminó', '2026-10-24 21:00:00', end: '2026-10-25 00:29:00');
        $this->event('Dos veces las 02:30', '2026-10-25 00:45:00');
        $this->event('Después del cambio', '2026-10-25 11:00:00');

        $events = $this->resolve('core.upcoming_events', [])->props['events'];

        $this->assertSame(['Dos veces las 02:30', 'Después del cambio'], array_column($events, 'title'));
        $this->assertSame('2026-10-25T02:45:00+02:00', $events[0]['startDate']);
        $this->assertSame('2026-10-25T12:00:00+01:00', $events[1]['startDate']);
    }

    #[DataProvider('storageZones')]
    public function test_upcoming_events_follow_the_chosen_number_within_one_to_five(bool $utc): void
    {
        $this->storeAs($utc);
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

    public function test_the_limit_options_are_one_to_five_and_three_is_the_default(): void
    {
        $field = app(UpcomingEventsFooterBlock::class)->settingsSchema()[0];

        $this->assertInstanceOf(Select::class, $field);
        $this->assertSame([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'], $field->getOptions());
        $this->assertSame(3, $field->getDefaultState());
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

    public function test_upcoming_events_cost_the_same_queries_whatever_the_number_of_events(): void
    {
        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));
        $this->event('Uno', '2026-06-16 18:00:00');
        $queries = function (): int {
            DB::flushQueryLog();
            $this->resolve('core.upcoming_events', ['limit' => 5]);

            return count(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'events')));
        };
        $this->resolve('core.upcoming_events', ['limit' => 5]);
        DB::enableQueryLog();
        $one = $queries();

        foreach (range(2, 5) as $day) {
            $this->event('Evento '.$day, sprintf('2026-06-%02d 18:00:00', 15 + $day));
        }

        $this->assertSame(1, $one);
        $this->assertSame(1, $queries());
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

    public function test_without_events_and_without_the_link_the_block_is_dropped_quietly(): void
    {
        $this->useUtcStorage();
        $log = Log::spy();

        $this->assertNull($this->resolveOrNull('core.upcoming_events', ['show_all_link' => false]));
        // With the link there is still something to say
        $this->assertSame([], $this->resolve('core.upcoming_events', [])->props['events']);
        $log->shouldNotHaveReceived('warning');
    }

    // ---- core.button ----

    public function test_button_draws_its_text_link_and_style(): void
    {
        $external = $this->resolve('core.button', ['label' => ' Únete ', 'url' => 'https://example.com/unete', 'style' => 'secondary']);
        $internal = $this->resolve('core.button', ['label' => 'Eventos', 'url' => '/eventos']);

        $this->assertSame('components/siteBlocks/ButtonBlock.vue', $external->component);
        $this->assertSame(['label' => 'Únete', 'url' => 'https://example.com/unete', 'variant' => 'secondary'], $external->props);
        $this->assertSame(['label' => 'Eventos', 'url' => '/eventos', 'variant' => 'primary'], $internal->props);
    }

    public function test_button_with_an_unknown_style_is_primary(): void
    {
        $this->assertSame('primary', $this->resolve('core.button', ['label' => 'A', 'url' => '/a', 'style' => 'huge'])->props['variant']);
    }

    public function test_button_with_a_link_that_is_not_allowed_is_not_drawn(): void
    {
        $log = Log::spy();

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
        $this->assertNull($block->props['logos'][1]['url']);
        $this->assertSame('/nosotros', $block->props['logos'][2]['url']);
        // Not in the library's index: as stored, with no size
        $this->assertNull($block->props['logos'][0]['width']);
        $this->assertNull($block->props['logos'][0]['height']);
    }

    public function test_a_logo_of_the_library_comes_scaled_with_the_size_it_is_drawn_at(): void
    {
        config(['cloudinary.cloud_name' => 'demo']);
        MediaFileModel::query()->create(['file_id' => 'footer/logos/wide', 'format' => 'png', 'width' => 800, 'height' => 200, 'resource_type' => 'image', 'folder' => 'footer/logos', 'bytes' => 1000, 'uploaded_at' => now(), 'last_seen_at' => now()]);
        MediaFileModel::query()->create(['file_id' => 'footer/logos/tiny', 'format' => 'png', 'width' => 30, 'height' => 20, 'resource_type' => 'image', 'folder' => 'footer/logos', 'bytes' => 1000, 'uploaded_at' => now(), 'last_seen_at' => now()]);
        MediaFileModel::query()->create(['file_id' => 'footer/logos/vector', 'format' => 'svg', 'width' => 100, 'height' => 50, 'resource_type' => 'image', 'folder' => 'footer/logos', 'bytes' => 1000, 'uploaded_at' => now(), 'last_seen_at' => now()]);

        $logos = $this->resolve('core.logos', ['logos' => [
            ['image' => 'footer/logos/wide.png', 'alt' => 'Ancho'],
            ['image' => 'footer/logos/tiny.png', 'alt' => 'Pequeño'],
            ['image' => 'footer/logos/vector.svg', 'alt' => 'Vector'],
        ]])->props['logos'];

        // Twice the drawn height, best format and quality
        $this->assertStringContainsString('/h_80,c_limit,q_auto,f_auto/', $logos[0]['src']);
        $this->assertSame(160, $logos[0]['width']);
        $this->assertSame(40, $logos[0]['height']);
        // Never drawn larger than it is
        $this->assertSame([30, 20], [$logos[1]['width'], $logos[1]['height']]);
        // A vector is served as uploaded
        $this->assertStringNotContainsString('c_limit', $logos[2]['src']);
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
    }

    public function test_logos_with_nothing_to_draw_are_dropped_quietly(): void
    {
        $log = Log::spy();

        $this->assertNull($this->resolveOrNull('core.logos', []));
        $this->assertNull($this->resolveOrNull('core.logos', ['logos' => 'x']));
        $this->assertNull($this->resolveOrNull('core.logos', ['logos' => [['image' => 'footer/logos/a.png', 'alt' => '']]]));
        $log->shouldNotHaveReceived('warning');
    }

    private function event(string $title, string $start, bool $published = true, ?string $location = null, ?string $slug = null, ?string $end = null): EventModel
    {
        $instant = CarbonImmutable::parse($start, 'UTC');

        return EventModel::factory()->create([
            'title' => $title,
            'slug' => $slug ?? Str::slug($title).'-'.Str::random(4),
            'is_published' => $published,
            'start_date' => $instant,
            'end_date' => $end !== null ? CarbonImmutable::parse($end, 'UTC') : $instant->addHours(3),
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
