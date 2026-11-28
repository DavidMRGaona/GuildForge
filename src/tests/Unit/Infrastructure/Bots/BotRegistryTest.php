<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bots;

use App\Application\Bots\Services\BotRegistryInterface;
use App\Infrastructure\Bots\BotRegistry;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;
use Tests\Support\Bots\FakeBot;
use Tests\TestCase;

final class BotRegistryTest extends TestCase
{
    public function test_the_registry_is_bound_as_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(BotRegistryInterface::class),
            $this->app->make(BotRegistryInterface::class),
        );
    }

    public function test_the_core_registers_no_bots(): void
    {
        $this->assertSame([], $this->app->make(BotRegistryInterface::class)->all());
    }

    public function test_a_valid_bot_is_listed_and_found_with_its_module(): void
    {
        $registry = new BotRegistry($this->app);
        $registry->registerMany([FakeBot::class], 'forum');

        $definition = $registry->find('forum.publisher');

        $this->assertNotNull($definition);
        $this->assertSame('forum.publisher', $definition->key);
        $this->assertSame('forum', $definition->module);
        $this->assertSame('Publicador del foro', $definition->label);
        $this->assertSame('Publica avisos en el foro.', $definition->description);
        $this->assertNull($definition->name);
        $this->assertSame(FakeBot::class, $definition->class);
        $this->assertSame([$definition], $registry->all());
        $this->assertNull($registry->find('forum.unknown'));
    }

    public function test_a_class_that_is_not_a_bot_is_left_out_with_a_warning(): void
    {
        $log = Log::spy();
        $registry = new BotRegistry($this->app, new Repository(new ArrayStore));
        $registry->register(stdClass::class, 'forum');

        $this->assertSame([], $registry->all());
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_an_invalid_key_is_left_out_with_a_warning(): void
    {
        $bot = new class extends FakeBot
        {
            public function key(): string
            {
                return 'Forum.Publisher';
            }
        };
        $log = Log::spy();
        $registry = new BotRegistry($this->app, new Repository(new ArrayStore));
        $registry->register($bot::class, 'forum');

        $this->assertSame([], $registry->all());
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_key_of_another_module_is_left_out_with_a_warning(): void
    {
        $bot = new class extends FakeBot
        {
            public function key(): string
            {
                return 'tables.x';
            }
        };
        $log = Log::spy();
        $registry = new BotRegistry($this->app, new Repository(new ArrayStore));
        $registry->register($bot::class, 'forum');

        $this->assertSame([], $registry->all());
        $this->assertNull($registry->find('tables.x'));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_two_classes_with_the_same_key_are_both_left_out(): void
    {
        $other = new class extends FakeBot {};
        $log = Log::spy();
        $registry = new BotRegistry($this->app, new Repository(new ArrayStore));
        $registry->registerMany([FakeBot::class, $other::class], 'forum');

        $this->assertSame([], $registry->all());
        $this->assertNull($registry->find('forum.publisher'));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_bot_whose_constructor_throws_is_left_out_without_its_message(): void
    {
        $bot = new class extends FakeBot
        {
            public static bool $fail = false;

            public function __construct()
            {
                if (self::$fail) {
                    throw new RuntimeException('No key for alice@example.com');
                }
            }
        };
        $bot::$fail = true;
        $log = Log::spy();
        $registry = new BotRegistry($this->app, new Repository(new ArrayStore));
        $registry->register($bot::class, 'forum');

        $this->assertSame([], $registry->all());
        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => ($context['exception'] ?? null) === RuntimeException::class
                && ! str_contains((string) json_encode($context), 'alice'),
        );
    }

    public function test_a_broken_bot_is_warned_about_once_a_day_across_requests(): void
    {
        $bot = new class extends FakeBot
        {
            public function key(): string
            {
                return 'Forum.Publisher';
            }
        };
        $cache = new Repository(new ArrayStore);
        $log = Log::spy();

        foreach ([1, 2] as $request) {
            $registry = new BotRegistry($this->app, $cache);
            $registry->register($bot::class, 'forum');
            $this->assertSame([], $registry->all());
        }

        $log->shouldHaveReceived('warning')->once();
        $this->assertTrue($cache->has(BotRegistry::WARNING_PREFIX.sha1('registry|'.$bot::class.'|key')));
    }
}
