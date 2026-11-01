<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Search;

use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Search\Services\SearchSourceRegistry;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Support\Search\FakeSearchSource;
use Tests\Support\Search\OtherFakeSearchSource;
use Tests\Support\Search\SameKeyFakeSearchSource;
use Tests\Support\Search\UnresolvableSearchSource;
use Tests\TestCase;

final class SearchSourceRegistryTest extends TestCase
{
    private SearchSourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SearchSourceRegistry($this->app);
    }

    #[Test]
    public function it_resolves_registered_sources(): void
    {
        $this->registry->register(FakeSearchSource::class);

        $sources = $this->registry->sources();

        $this->assertCount(1, $sources);
        $this->assertInstanceOf(FakeSearchSource::class, $sources[0]);
    }

    #[Test]
    public function it_registers_many_sources_for_a_module(): void
    {
        $this->registry->registerMany([FakeSearchSource::class, OtherFakeSearchSource::class], 'demo');

        $this->assertCount(2, $this->registry->sources());

        $this->registry->unregisterModule('demo');

        $this->assertSame([], $this->registry->sources());
    }

    #[Test]
    public function unregistering_a_module_keeps_the_core_sources(): void
    {
        $this->registry->register(FakeSearchSource::class);
        $this->registry->register(OtherFakeSearchSource::class, 'demo');

        $this->registry->unregisterModule('demo');

        $this->assertCount(1, $this->registry->sources());
    }

    #[Test]
    public function clear_removes_every_source(): void
    {
        $this->registry->register(FakeSearchSource::class);

        $this->registry->clear();

        $this->assertSame([], $this->registry->sources());
    }

    #[Test]
    public function it_skips_and_logs_sources_that_cannot_be_resolved(): void
    {
        $log = Log::spy();
        $this->registry->registerMany([UnresolvableSearchSource::class, FakeSearchSource::class]);

        $sources = $this->registry->sources();

        $this->assertCount(1, $sources);
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_skips_classes_that_are_not_search_sources(): void
    {
        $log = Log::spy();
        $this->registry->register(stdClass::class);

        $this->assertSame([], $this->registry->sources());
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_skips_a_source_whose_key_is_already_taken(): void
    {
        $log = Log::spy();
        $this->registry->registerMany([FakeSearchSource::class, SameKeyFakeSearchSource::class]);

        $this->assertCount(1, $this->registry->sources());
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_is_bound_as_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(SearchSourceRegistryInterface::class),
            $this->app->make(SearchSourceRegistryInterface::class),
        );
    }
}
