<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use App\Infrastructure\Knowledge\Services\KnowledgeCapabilityRegistry;
use Illuminate\Support\Facades\Log;
use stdClass;
use Tests\Support\Knowledge\FakeKnowledgeCapability;
use Tests\Support\Knowledge\SameKeyKnowledgeCapability;
use Tests\Support\Knowledge\ThrowingKnowledgeCapability;
use Tests\TestCase;

final class KnowledgeCapabilityRegistryTest extends TestCase
{
    private function registry(): KnowledgeCapabilityRegistry
    {
        return new KnowledgeCapabilityRegistry($this->app);
    }

    public function test_evaluate_returns_the_capability_result(): void
    {
        $registry = $this->registry();
        $registry->register(FakeKnowledgeCapability::class);

        $this->assertTrue($registry->evaluate('fake.can_do_it', new stdClass));
        $this->assertSame(['fake.can_do_it'], $registry->keys());
    }

    public function test_unknown_keys_evaluate_to_null(): void
    {
        $this->assertNull($this->registry()->evaluate('nope.nothing', new stdClass));
    }

    public function test_a_throwing_capability_evaluates_to_null_and_logs(): void
    {
        Log::shouldReceive('warning')
            ->withArgs(fn (string $message): bool => str_starts_with($message, '[KnowledgeCapabilityRegistry]'))
            ->atLeast()->once();
        $registry = $this->registry();
        $registry->register(ThrowingKnowledgeCapability::class);

        $this->assertNull($registry->evaluate('fake.throws', new stdClass));

    }

    public function test_an_unresolvable_class_is_skipped(): void
    {
        $registry = $this->registry();
        // An interface cannot be instantiated by the container.
        $registry->register(KnowledgeCapabilityInterface::class);

        $this->assertSame([], $registry->keys());
    }

    public function test_first_registration_of_a_key_wins_and_duplicates_are_logged(): void
    {
        Log::shouldReceive('warning')
            ->withArgs(fn (string $message): bool => str_starts_with($message, '[KnowledgeCapabilityRegistry]'))
            ->atLeast()->once();
        $registry = $this->registry();
        $registry->registerMany([FakeKnowledgeCapability::class, SameKeyKnowledgeCapability::class]);

        $this->assertSame(['fake.can_do_it'], $registry->keys());
        $this->assertTrue($registry->evaluate('fake.can_do_it', new stdClass));
    }

    public function test_unregister_module_removes_its_capabilities(): void
    {
        $registry = $this->registry();
        $registry->register(FakeKnowledgeCapability::class, 'example-module');
        $registry->register(ThrowingKnowledgeCapability::class);

        $registry->unregisterModule('example-module');

        $this->assertSame(['fake.throws'], $registry->keys());

        $registry->clear();
        $this->assertSame([], $registry->keys());
    }
}
