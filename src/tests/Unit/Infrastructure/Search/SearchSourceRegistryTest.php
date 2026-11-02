<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Search;

use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Search\Services\SearchSourceRegistry;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Support\Search\FakeIndexedSearchSource;
use Tests\Support\Search\FakeSearchSource;
use Tests\Support\Search\OtherFakeIndexedSearchSource;
use Tests\Support\Search\SameKeyFakeSearchSource;
use Tests\Support\Search\SameTypeFakeIndexedSearchSource;
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
        $this->registry->registerMany([FakeIndexedSearchSource::class, OtherFakeIndexedSearchSource::class], 'demo');

        $this->assertCount(2, $this->registry->indexedSources());

        $this->registry->unregisterModule('demo');

        $this->assertSame([], $this->registry->indexedSources());
    }

    #[Test]
    public function unregistering_a_module_keeps_the_core_sources(): void
    {
        $this->registry->register(FakeSearchSource::class);
        $this->registry->register(OtherFakeIndexedSearchSource::class, 'demo');

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
    public function indexed_sources_are_resolved_by_type_and_duplicates_skipped(): void
    {
        $log = Log::spy();
        $this->registry->registerMany([
            FakeIndexedSearchSource::class,
            SameTypeFakeIndexedSearchSource::class,
            OtherFakeIndexedSearchSource::class,
        ], 'demo');

        $indexed = $this->registry->indexedSources();

        $this->assertCount(2, $indexed);
        $this->assertInstanceOf(FakeIndexedSearchSource::class, $indexed[0]);
        $this->assertSame('other-indexed', $indexed[1]->type());
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function live_sources_exclude_indexed_ones(): void
    {
        $this->registry->registerMany([FakeSearchSource::class, FakeIndexedSearchSource::class]);

        $this->assertCount(1, $this->registry->sources());
        $this->assertInstanceOf(FakeSearchSource::class, $this->registry->sources()[0]);
        $this->assertCount(1, $this->registry->indexedSources());
    }

    #[Test]
    public function module_live_source_is_ignored_with_a_warning(): void
    {
        $log = Log::spy();
        $this->registry->register(FakeSearchSource::class, 'demo');

        $this->assertSame([], $this->registry->sources());
        $this->assertSame([], $this->registry->indexedSources());
        $log->shouldHaveReceived('warning');
    }

    #[Test]
    public function unregister_module_drops_its_indexed_sources(): void
    {
        $this->registry->register(FakeIndexedSearchSource::class, 'demo');
        $this->registry->register(OtherFakeIndexedSearchSource::class);

        $this->registry->unregisterModule('demo');

        $this->assertCount(1, $this->registry->indexedSources());
    }

    #[Test]
    public function module_of_returns_owner_or_null(): void
    {
        $this->registry->register(FakeIndexedSearchSource::class, 'demo');
        $this->registry->register(OtherFakeIndexedSearchSource::class);

        $this->assertSame('demo', $this->registry->moduleOf('fake-indexed'));
        $this->assertNull($this->registry->moduleOf('other-indexed'));
        $this->assertNull($this->registry->moduleOf('missing'));
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
