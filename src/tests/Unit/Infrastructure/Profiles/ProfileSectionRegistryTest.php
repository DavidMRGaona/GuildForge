<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileRegistrationDTO;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Domain\Profiles\ProfileRegion;
use App\Infrastructure\Profiles\ProfileSectionRegistry;
use App\Infrastructure\Profiles\Sections\AboutProfileSection;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Tests\Support\Profiles\FakeProfileSection;
use Tests\TestCase;

final class ProfileSectionRegistryTest extends TestCase
{
    private ProfileSectionRegistry $registry;

    private int $bindings = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProfileSectionRegistry($this->app, new Repository(new ArrayStore));
    }

    public function test_the_registry_is_a_singleton_holding_the_core_sections(): void
    {
        $registry = $this->app->make(ProfileSectionRegistryInterface::class);

        $this->assertSame($registry, $this->app->make(ProfileSectionRegistryInterface::class));
        $about = $registry->find('core.about');
        $this->assertSame(AboutProfileSection::class, $about?->class);
        $this->assertNull($about->module);
    }

    public function test_a_module_section_is_found_by_key_with_its_module(): void
    {
        $this->registry->register($this->section('forum.latest_posts'), 'forum');

        $registration = $this->registry->find('forum.latest_posts');
        $this->assertSame('forum.latest_posts', $registration?->key);
        $this->assertSame('forum', $registration->module);
        $this->assertSame(100, $registration->order);
        $this->assertNull($this->registry->find('forum.other'));
    }

    public function test_the_registry_keeps_what_it_read_never_an_instance(): void
    {
        $built = 0;
        $this->app->bind('counted-section', static function () use (&$built): ProfileSectionInterface {
            $built++;

            return new class extends FakeProfileSection
            {
                public function key(): string
                {
                    return 'forum.counted';
                }
            };
        });
        $this->registry->register('counted-section', 'forum');

        $this->assertSame('counted-section', $this->registry->find('forum.counted')?->class);
        $this->registry->all();

        // Built once, only to check it: drawing builds its own in each request or job
        $this->assertSame(1, $built);
    }

    public function test_sections_are_listed_by_default_order_then_key(): void
    {
        $this->registry->registerMany([
            $this->section('forum.b', 20),
            $this->section('forum.a', 20),
            $this->section('forum.first', 5),
        ], 'forum');

        $this->assertSame(['forum.first', 'forum.a', 'forum.b'], array_map(
            static fn (ProfileRegistrationDTO $registration): string => $registration->key,
            $this->registry->all(),
        ));
    }

    public function test_a_module_s_sections_are_forgotten_when_it_is_unregistered(): void
    {
        $this->registry->register($this->section('forum.latest_posts'), 'forum');
        $this->registry->unregisterModule('forum');

        $this->assertNull($this->registry->find('forum.latest_posts'));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function foreignKeys(): iterable
    {
        yield 'another module' => ['game-tables.tables', 'forum'];
        yield 'the core, from a module' => ['core.about_me', 'forum'];
        yield 'a module, from the core' => ['forum.latest_posts', null];
        yield 'a module called core' => ['core.about_me', 'core'];
        yield 'invalid' => ['Forum Posts', 'forum'];
    }

    #[DataProvider('foreignKeys')]
    public function test_a_section_whose_key_is_not_its_owner_s_is_left_out_with_a_warning(string $key, ?string $module): void
    {
        $log = Log::spy();
        $this->registry->register($this->section($key), $module);

        $this->assertSame([], $this->registry->all());
        $log->shouldHaveReceived('warning')->once();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidComponents(): iterable
    {
        yield 'outside components' => ['pages/Section.vue'];
        yield 'climbing out' => ['components/../../secret.vue'];
        yield 'not a vue file' => ['components/Section.ts'];
        yield 'absolute' => ['/components/Section.vue'];
    }

    #[DataProvider('invalidComponents')]
    public function test_a_section_with_an_invalid_component_path_is_left_out(string $component): void
    {
        $section = new class($component) extends FakeProfileSection
        {
            public function __construct(private readonly string $path = '') {}

            public function key(): string
            {
                return 'forum.latest_posts';
            }

            public function component(): string
            {
                return $this->path;
            }
        };
        $this->app->bind('component-section', static fn (): ProfileSectionInterface => $section);
        Log::spy();

        $this->registry->register('component-section', 'forum');

        $this->assertNull($this->registry->find('forum.latest_posts'));
    }

    public function test_two_sections_with_one_key_are_both_left_out(): void
    {
        $log = Log::spy();
        $this->registry->register($this->section('forum.latest_posts'), 'forum');
        $this->registry->register($this->section('forum.latest_posts'), 'forum');

        $this->assertNull($this->registry->find('forum.latest_posts'));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_class_registered_by_a_second_owner_keeps_the_first(): void
    {
        $log = Log::spy();
        $section = $this->section('forum.latest_posts');
        $this->registry->register($section, 'forum');
        $this->registry->register($section, 'game-tables');

        $this->assertSame('forum', $this->registry->find('forum.latest_posts')?->module);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_class_that_is_not_a_section_or_cannot_be_built_is_left_out(): void
    {
        $log = Log::spy();
        $this->registry->register(stdClass::class, 'forum');
        $this->app->bind('broken-section', static fn (): never => throw new RuntimeException('Secret alice@example.com'));
        $this->registry->register('broken-section', 'forum');

        $this->assertSame([], $this->registry->all());
        $log->shouldHaveReceived('warning')->twice()->withArgs(
            static fn (string $message, array $context): bool => ! str_contains((string) json_encode($context), 'alice'),
        );
    }

    public function test_a_section_whose_region_is_not_a_profile_region_is_left_out(): void
    {
        $section = new class extends FakeProfileSection
        {
            public function key(): string
            {
                return 'forum.latest_posts';
            }

            // A module's code may not keep the promise of its signature at runtime
            public function region(): ProfileRegion
            {
                throw new RuntimeException('No region');
            }
        };
        Log::spy();

        $this->registry->register($section::class, 'forum');

        $this->assertSame([], $this->registry->all());
    }

    public function test_warnings_are_kept_apart_for_a_day(): void
    {
        $cache = new Repository(new ArrayStore);
        $log = Log::spy();
        $class = $this->section('game-tables.tables');

        foreach ([1, 2] as $request) {
            $registry = new ProfileSectionRegistry($this->app, $cache);
            $registry->register($class, 'forum');
            $registry->all();
        }

        $log->shouldHaveReceived('warning')->once();
    }

    private function section(string $key, int $order = 100): string
    {
        $section = new class($key, $order) extends FakeProfileSection
        {
            public function __construct(
                private readonly string $sectionKey = '',
                private readonly int $position = 100,
            ) {}

            public function key(): string
            {
                return $this->sectionKey;
            }

            public function order(): int
            {
                return $this->position;
            }
        };

        // Built from the container: a binding per call, so two calls are two registrations
        $id = 'test-section-'.(++$this->bindings);
        $this->app->bind($id, static fn (): ProfileSectionInterface => $section);

        return $id;
    }
}
