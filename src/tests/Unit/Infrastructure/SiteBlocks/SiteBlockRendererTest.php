<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\SiteBlocks;

use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\SiteBlocks\SiteBlockRegistry;
use App\Infrastructure\SiteBlocks\SiteBlockRenderer;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Support\SiteBlocks\FakeSiteBlock;
use Tests\TestCase;

final class SiteBlockRendererTest extends TestCase
{
    private SiteBlockRegistry $registry;

    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new SiteBlockRegistry($this->app);
        $this->cache = new Repository(new ArrayStore);
    }

    public function test_the_renderer_is_scoped_to_the_request(): void
    {
        $first = $this->app->make(SiteBlockRendererInterface::class);
        $this->assertSame($first, $this->app->make(SiteBlockRendererInterface::class));

        $this->app->forgetScopedInstances();

        $this->assertNotSame($first, $this->app->make(SiteBlockRendererInterface::class));
    }

    public function test_a_module_block_is_resolved_with_its_module_component_and_props(): void
    {
        $this->registry->register($this->subscribeBlock()::class, 'newsletter');
        $user = new UserModel;

        $resolved = $this->renderer()->resolveBlock('newsletter.subscribe', ['heading' => 'Suscríbete'], SiteBlockPlacement::Footer, $user);

        $this->assertInstanceOf(ResolvedSiteBlockDTO::class, $resolved);
        $this->assertSame([
            'key' => 'newsletter.subscribe',
            'module' => 'newsletter',
            'component' => 'components/SubscribeBlock.vue',
            'props' => ['settings' => ['heading' => 'Suscríbete'], 'signedIn' => true],
        ], $resolved->toArray());
    }

    public function test_a_core_block_has_no_module(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'core.example';
            }

            public function component(): string
            {
                return 'components/siteBlocks/ExampleBlock.vue';
            }
        };
        $this->registry->register($block::class);

        $resolved = $this->renderer()->resolveBlock('core.example', [], SiteBlockPlacement::Popup, null);

        $this->assertInstanceOf(ResolvedSiteBlockDTO::class, $resolved);
        $this->assertNull($resolved->module);
        $this->assertSame('components/siteBlocks/ExampleBlock.vue', $resolved->component);
        $this->assertSame(['settings' => [], 'signedIn' => false], $resolved->props);
    }

    public function test_empty_props_are_sent_as_an_object(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function props(array $settings, ?Authenticatable $user): array
            {
                return [];
            }
        };
        $this->registry->register($block::class, 'newsletter');

        $resolved = $this->renderer()->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null);

        $this->assertSame('{"key":"newsletter.subscribe","module":"newsletter","component":"components\/ExampleBlock.vue","props":{}}', json_encode($resolved?->toArray()));
    }

    public function test_a_module_block_only_gets_flat_settings(): void
    {
        $this->registry->register($this->subscribeBlock()::class, 'newsletter');

        $resolved = $this->renderer()->resolveBlock('newsletter.subscribe', [
            'heading' => 'Suscríbete',
            'limit' => 3,
            'ratio' => 0.5,
            'compact' => false,
            'link' => null,
            'images' => ['a.png'],
            'nested' => ['label' => 'x'],
        ], SiteBlockPlacement::Footer, null);

        $this->assertSame(
            ['heading' => 'Suscríbete', 'limit' => 3, 'ratio' => 0.5, 'compact' => false, 'link' => null],
            $resolved?->props['settings'] ?? null,
        );
    }

    public function test_an_unknown_key_is_left_out_and_warned_about_once(): void
    {
        $log = Log::spy();
        $renderer = $this->renderer();

        $this->assertNull($renderer->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
        $this->assertNull($renderer->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
        // A later request (another renderer sharing the cache) does not warn either
        $this->assertNull($this->renderer()->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));

        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_block_asked_for_a_placement_it_does_not_offer_is_left_out(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function placements(): array
            {
                return [SiteBlockPlacement::Popup];
            }
        };
        $this->registry->register($block::class, 'newsletter');

        $this->assertNull($this->renderer()->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
        $this->assertNotNull($this->renderer()->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Popup, null));
    }

    public function test_an_unavailable_block_is_left_out_without_a_warning(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function isAvailable(): bool
            {
                return false;
            }
        };
        $this->registry->register($block::class, 'newsletter');
        $log = Log::spy();

        $this->assertNull($this->renderer()->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
        $log->shouldNotHaveReceived('warning');
    }

    public function test_a_block_whose_availability_check_throws_is_left_out_and_warned_about_once(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function isAvailable(): bool
            {
                throw new RuntimeException('Provider unreachable');
            }
        };
        $this->registry->register($block::class, 'newsletter');
        $log = Log::spy();
        $renderer = $this->renderer();

        $this->assertNull($renderer->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
        $this->assertNull($renderer->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_block_whose_props_throw_is_left_out(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function props(array $settings, ?Authenticatable $user): array
            {
                throw new RuntimeException('No props');
            }
        };
        $this->registry->register($block::class, 'newsletter');
        $log = Log::spy();

        $this->assertNull($this->renderer()->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_block_whose_props_cannot_be_sent_to_the_browser_is_left_out(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function props(array $settings, ?Authenticatable $user): array
            {
                return ['ratio' => NAN];
            }
        };
        $this->registry->register($block::class, 'newsletter');

        $this->assertNull($this->renderer()->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
    }

    public function test_a_block_whose_props_are_a_list_is_left_out(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function props(array $settings, ?Authenticatable $user): array
            {
                /** @phpstan-ignore return.type (a broken module block) */
                return ['a', 'b'];
            }
        };
        $this->registry->register($block::class, 'newsletter');

        $this->assertNull($this->renderer()->resolveBlock('newsletter.subscribe', [], SiteBlockPlacement::Footer, null));
    }

    public function test_a_list_of_stored_blocks_keeps_the_order_and_drops_what_cannot_be_drawn(): void
    {
        $this->registry->register($this->subscribeBlock()::class, 'newsletter');
        $core = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'core.example';
            }
        };
        $this->registry->register($core::class);

        $resolved = $this->renderer()->resolve([
            ['key' => 'core.example', 'settings' => ['a' => 1]],
            ['key' => 'announcements.gone'],
            'not a block',
            ['settings' => []],
            ['key' => 'newsletter.subscribe', 'settings' => 'not settings'],
            ['key' => 'newsletter.subscribe'],
        ], SiteBlockPlacement::Footer, null);

        $this->assertSame(['core.example', 'newsletter.subscribe'], array_map(
            static fn (ResolvedSiteBlockDTO $block): string => $block->key,
            $resolved,
        ));
        $this->assertSame(['a' => 1], $resolved[0]->props['settings']);
        $this->assertSame([], $resolved[1]->props['settings']);
    }

    public function test_only_available_blocks_are_offered_for_a_placement(): void
    {
        $unavailable = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.unavailable';
            }

            public function isAvailable(): bool
            {
                return false;
            }
        };
        $failing = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.failing';
            }

            public function isAvailable(): bool
            {
                throw new RuntimeException('Provider unreachable');
            }
        };
        $this->registry->registerMany([$this->subscribeBlock()::class, $unavailable::class, $failing::class], 'newsletter');

        $offered = $this->renderer()->available(SiteBlockPlacement::Footer);

        $this->assertSame(['newsletter.subscribe'], array_map(static fn ($block): string => $block->key(), $offered));
    }

    private function renderer(): SiteBlockRenderer
    {
        return new SiteBlockRenderer($this->registry, $this->cache);
    }

    private function subscribeBlock(): FakeSiteBlock
    {
        return new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function component(): string
            {
                return 'components/SubscribeBlock.vue';
            }
        };
    }
}
