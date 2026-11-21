<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Domain\Activity\ActivityRole;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Activity\ActivitySourceContractTests;
use Tests\Support\Activity\InMemoryActivitySource;
use Tests\TestCase;

/** The contract checks module sources run, against a source that keeps it */
final class InMemoryActivitySourceContractTest extends TestCase
{
    use ActivitySourceContractTests;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        InMemoryActivitySource::reset();
        // A module's source is registered by its provider; this one is the core's, by hand
        $this->app->make(ActivitySourceRegistryInterface::class)->register(InMemoryActivitySource::class);
    }

    protected function tearDown(): void
    {
        InMemoryActivitySource::reset();

        parent::tearDown();
    }

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(InMemoryActivitySource::class);
    }

    protected function activitySourceOwner(): string
    {
        return 'core';
    }

    protected function createCountedActivitySubject(): string
    {
        $id = (string) Str::uuid7();
        $system = new ActivityAttributeValueDTO('pf2', 'Pathfinder 2');

        InMemoryActivitySource::put($id, [
            new ActivityFactDTO((string) Str::uuid(), ActivityRole::Director, new DateTimeImmutable, ['game_system' => $system]),
            new ActivityFactDTO((string) Str::uuid(), ActivityRole::Player, new DateTimeImmutable, ['game_system' => $system], 1),
        ]);

        return $id;
    }
}
