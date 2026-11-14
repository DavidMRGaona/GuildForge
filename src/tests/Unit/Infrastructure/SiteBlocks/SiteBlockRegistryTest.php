<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\SiteBlocks;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Infrastructure\SiteBlocks\SiteBlockRegistry;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\SiteBlocks\FakeSiteBlock;
use Tests\TestCase;

final class SiteBlockRegistryTest extends TestCase
{
    public function test_the_registry_is_bound_as_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(SiteBlockRegistryInterface::class),
            $this->app->make(SiteBlockRegistryInterface::class),
        );
    }

    public function test_core_and_module_blocks_are_listed_found_and_filtered_by_placement(): void
    {
        $registry = $this->registry();
        $registry->register($this->coreBlock()::class);
        $registry->registerMany([$this->popupOnlyBlock()::class], 'newsletter');

        $this->assertSame(['core.example', 'newsletter.subscribe'], $this->keys($registry->all()));
        $this->assertSame(['core.example'], $this->keys($registry->forPlacement(SiteBlockPlacement::Footer)));
        $this->assertSame(['core.example', 'newsletter.subscribe'], $this->keys($registry->forPlacement(SiteBlockPlacement::Popup)));
        $this->assertSame('newsletter.subscribe', $registry->find('newsletter.subscribe')?->key());
        $this->assertNull($registry->find('newsletter.unknown'));
    }

    public function test_a_module_can_only_register_keys_that_start_with_its_name(): void
    {
        $log = Log::spy();
        $registry = $this->registry();
        $registry->register($this->popupOnlyBlock()::class, 'announcements');
        $registry->register($this->coreBlock()::class, 'announcements');

        $this->assertSame([], $registry->all());
        $this->assertNull($registry->find('newsletter.subscribe'));
        $this->assertNull($registry->find('core.example'));
        $log->shouldHaveReceived('warning')->twice();
    }

    public function test_the_core_can_only_register_core_keys(): void
    {
        $registry = $this->registry();
        $registry->register($this->popupOnlyBlock()::class);

        $this->assertSame([], $registry->all());
    }

    public function test_a_module_called_core_cannot_register_core_keys(): void
    {
        $registry = $this->registry();
        $registry->register($this->coreBlock()::class, 'core');

        $this->assertSame([], $registry->all());
    }

    public function test_a_block_with_an_invalid_key_is_left_out(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.Subscribe';
            }
        };

        $registry = $this->registry();
        $registry->register($block::class, 'newsletter');

        $this->assertSame([], $registry->all());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidComponents(): array
    {
        return [
            'outside components' => ['pages/Subscribe.vue'],
            'climbing out' => ['components/../../secret.vue'],
            'absolute' => ['/components/Subscribe.vue'],
            'not a vue file' => ['components/Subscribe.ts'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidComponents')]
    public function test_a_block_with_an_invalid_component_path_is_left_out(string $component): void
    {
        $block = new class extends FakeSiteBlock
        {
            public static string $component = '';

            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function component(): string
            {
                return self::$component;
            }
        };
        $block::$component = $component;

        $registry = $this->registry();
        $registry->register($block::class, 'newsletter');

        $this->assertSame([], $registry->all());
    }

    public function test_a_block_with_a_placement_that_is_not_a_placement_is_left_out(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function placements(): array
            {
                /** @phpstan-ignore return.type (a broken module block) */
                return ['footer'];
            }
        };

        $registry = $this->registry();
        $registry->register($block::class, 'newsletter');

        $this->assertSame([], $registry->all());
    }

    public function test_a_class_that_is_not_a_site_block_or_cannot_be_built_is_left_out(): void
    {
        $failing = new class extends FakeSiteBlock
        {
            public static bool $fail = false;

            public function __construct()
            {
                if (self::$fail) {
                    throw new RuntimeException('Cannot be built');
                }
            }

            public function key(): string
            {
                return 'newsletter.subscribe';
            }
        };
        $failing::$fail = true;

        $log = Log::spy();
        $registry = $this->registry();
        $registry->register(\stdClass::class, 'newsletter');
        $registry->register($failing::class, 'newsletter');

        $this->assertSame([], $registry->all());
        // Asked again, it does not warn again
        $this->assertSame([], $registry->forPlacement(SiteBlockPlacement::Footer));
        $log->shouldHaveReceived('warning')->twice();
    }

    public function test_a_broken_block_is_warned_about_once_a_day_across_requests(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.Subscribe';
            }
        };
        $cache = new Repository(new ArrayStore);
        $log = Log::spy();

        // Each request builds its own registry; the cache is what they share
        foreach ([1, 2] as $request) {
            $registry = new SiteBlockRegistry($this->app, cache: $cache);
            $registry->register($block::class, 'newsletter');
            $this->assertSame([], $registry->all());
        }

        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_block_that_cannot_be_built_logs_the_exception_class_but_never_its_message(): void
    {
        $failing = new class extends FakeSiteBlock
        {
            public static bool $fail = false;

            public function __construct()
            {
                if (self::$fail) {
                    throw new RuntimeException('No key for alice@example.com');
                }
            }

            public function key(): string
            {
                return 'newsletter.subscribe';
            }
        };
        $failing::$fail = true;
        $log = Log::spy();

        $registry = $this->registry();
        $registry->register($failing::class, 'newsletter');
        $this->assertSame([], $registry->all());

        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => ($context['exception'] ?? null) === RuntimeException::class
                && ! str_contains((string) json_encode($context), 'alice'),
        );
    }

    public function test_a_class_registered_by_a_second_owner_keeps_the_first_one(): void
    {
        $log = Log::spy();
        $registry = $this->registry();
        $registry->register($this->popupOnlyBlock()::class, 'newsletter');
        $registry->register($this->popupOnlyBlock()::class, 'announcements');
        // Registering it again for the same owner changes nothing and says nothing
        $registry->register($this->popupOnlyBlock()::class, 'newsletter');

        $this->assertSame('newsletter.subscribe', $registry->find('newsletter.subscribe')?->key());

        $registry->unregisterModule('announcements');
        $this->assertNotNull($registry->find('newsletter.subscribe'));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_the_setting_names_of_a_module_block_are_its_fields(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function settingsSchema(): array
            {
                return [TextInput::make('heading'), TextInput::make('button_label')];
            }
        };

        $registry = $this->registry();
        $registry->register($block::class, 'newsletter');
        $registry->register($this->coreBlock()::class);

        $this->assertSame(['heading', 'button_label'], $registry->settingNames('newsletter.subscribe'));
        $this->assertNull($registry->settingNames('core.example'));
        $this->assertNull($registry->settingNames('newsletter.unknown'));
    }

    public function test_two_blocks_with_the_same_key_are_both_left_out(): void
    {
        $other = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }
        };

        $registry = $this->registry();
        $registry->register($this->popupOnlyBlock()::class, 'newsletter');
        $registry->register($other::class, 'newsletter');

        $this->assertSame([], $registry->all());
        $this->assertNull($registry->find('newsletter.subscribe'));
    }

    public function test_a_module_block_with_settings_that_are_not_flat_is_left_out(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function settingsSchema(): array
            {
                return [TextInput::make('heading'), FileUpload::make('image')];
            }
        };

        $registry = $this->registry();
        $registry->register($block::class, 'newsletter');

        $this->assertSame([], $registry->all());
    }

    public function test_a_module_block_whose_settings_cannot_be_built_is_left_out(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function settingsSchema(): array
            {
                throw new RuntimeException('No settings now');
            }
        };

        $registry = $this->registry();
        $registry->register($block::class, 'newsletter');

        $this->assertSame([], $registry->all());
    }

    public function test_a_module_block_with_flat_settings_is_kept(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function settingsSchema(): array
            {
                return [TextInput::make('heading'), TextInput::make('button_label')];
            }
        };

        $registry = $this->registry();
        $registry->register($block::class, 'newsletter');

        $this->assertSame(['newsletter.subscribe'], $this->keys($registry->all()));
    }

    public function test_core_blocks_may_use_any_field(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'core.logos';
            }

            public function settingsSchema(): array
            {
                return [FileUpload::make('images')];
            }
        };

        $registry = $this->registry();
        $registry->register($block::class);

        $this->assertSame(['core.logos'], $this->keys($registry->all()));
    }

    public function test_the_blocks_of_a_module_can_be_forgotten(): void
    {
        $registry = $this->registry();
        $registry->register($this->coreBlock()::class);
        $registry->register($this->popupOnlyBlock()::class, 'newsletter');

        $this->assertNotNull($registry->find('newsletter.subscribe'));

        $registry->unregisterModule('newsletter');

        $this->assertSame(['core.example'], $this->keys($registry->all()));
        $this->assertNull($registry->find('newsletter.subscribe'));
    }

    private function registry(): SiteBlockRegistry
    {
        return new SiteBlockRegistry($this->app, cache: new Repository(new ArrayStore));
    }

    private function coreBlock(): FakeSiteBlock
    {
        return new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'core.example';
            }
        };
    }

    private function popupOnlyBlock(): FakeSiteBlock
    {
        return new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'newsletter.subscribe';
            }

            public function placements(): array
            {
                return [SiteBlockPlacement::Popup];
            }

            public function component(): string
            {
                return 'components/SubscribeBlock.vue';
            }
        };
    }

    /**
     * @param  list<SiteBlockInterface>  $blocks
     * @return list<string>
     */
    private function keys(array $blocks): array
    {
        return array_map(static fn ($block): string => $block->key(), $blocks);
    }
}
