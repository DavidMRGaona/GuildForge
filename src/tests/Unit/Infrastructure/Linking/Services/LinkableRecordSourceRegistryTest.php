<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Linking\Services;

use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use App\Infrastructure\Linking\Services\LinkableRecordSourceRegistry;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Support\Linking\FakeLinkableRecordSource;
use Tests\Support\Linking\InvalidTypeLinkableRecordSource;
use Tests\Support\Linking\OtherFakeLinkableRecordSource;
use Tests\Support\Linking\SameTypeFakeLinkableRecordSource;
use Tests\Support\Linking\ThrowingTypeLinkableRecordSource;
use Tests\Support\Linking\UnresolvableLinkableRecordSource;
use Tests\TestCase;

final class LinkableRecordSourceRegistryTest extends TestCase
{
    private LinkableRecordSourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new LinkableRecordSourceRegistry($this->app);
    }

    #[Test]
    public function it_can_register_a_source_class(): void
    {
        $this->registry->register(FakeLinkableRecordSource::class);

        $this->assertSame([FakeLinkableRecordSource::class], $this->registry->all());
    }

    #[Test]
    public function it_can_register_many_source_classes_with_a_module(): void
    {
        $this->registry->registerMany([
            FakeLinkableRecordSource::class,
            OtherFakeLinkableRecordSource::class,
        ], 'example-module');

        $this->assertSame(
            [FakeLinkableRecordSource::class, OtherFakeLinkableRecordSource::class],
            $this->registry->all(),
        );
    }

    #[Test]
    public function it_does_not_duplicate_a_class_registered_twice(): void
    {
        $this->registry->register(FakeLinkableRecordSource::class);
        $this->registry->register(FakeLinkableRecordSource::class);

        $this->assertCount(1, $this->registry->all());
    }

    #[Test]
    public function it_can_unregister_module_source_classes(): void
    {
        $this->registry->registerMany([FakeLinkableRecordSource::class], 'example-module');
        $this->registry->registerMany([OtherFakeLinkableRecordSource::class], 'another-module');

        $this->registry->unregisterModule('example-module');

        $this->assertSame([OtherFakeLinkableRecordSource::class], $this->registry->all());
    }

    #[Test]
    public function it_can_clear_all_source_classes(): void
    {
        $this->registry->registerMany([
            FakeLinkableRecordSource::class,
            OtherFakeLinkableRecordSource::class,
        ], 'example-module');

        $this->registry->clear();

        $this->assertSame([], $this->registry->all());
        $this->assertSame([], $this->registry->sources());
    }

    #[Test]
    public function it_resolves_sources_keyed_by_type(): void
    {
        $this->registry->registerMany([
            FakeLinkableRecordSource::class,
            OtherFakeLinkableRecordSource::class,
        ], 'example-module');

        $sources = $this->registry->sources();

        $this->assertSame(['fake', 'other-fake'], array_keys($sources));
        $this->assertInstanceOf(FakeLinkableRecordSource::class, $sources['fake']);
        $this->assertInstanceOf(OtherFakeLinkableRecordSource::class, $sources['other-fake']);
    }

    #[Test]
    public function it_finds_a_source_by_type(): void
    {
        $this->registry->registerMany([
            FakeLinkableRecordSource::class,
            OtherFakeLinkableRecordSource::class,
        ], 'example-module');

        $this->assertInstanceOf(OtherFakeLinkableRecordSource::class, $this->registry->source('other-fake'));
        $this->assertNull($this->registry->source('unknown'));
    }

    #[Test]
    public function it_skips_a_class_that_cannot_be_resolved(): void
    {
        $log = Log::spy();

        $this->registry->register(UnresolvableLinkableRecordSource::class, 'broken-module');
        $this->registry->register(FakeLinkableRecordSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_skips_a_class_that_is_not_a_linkable_record_source(): void
    {
        $log = Log::spy();

        $this->registry->register(stdClass::class, 'broken-module');
        $this->registry->register(FakeLinkableRecordSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $this->assertNull($this->registry->source('stdClass'));
        $log->shouldHaveReceived('warning')->twice();
    }

    #[Test]
    public function it_skips_a_source_whose_type_cannot_be_read(): void
    {
        $log = Log::spy();

        $this->registry->register(ThrowingTypeLinkableRecordSource::class, 'broken-module');
        $this->registry->register(FakeLinkableRecordSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_skips_a_source_whose_type_is_not_kebab_case(): void
    {
        $log = Log::spy();

        $this->registry->register(InvalidTypeLinkableRecordSource::class, 'broken-module');
        $this->registry->register(FakeLinkableRecordSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_keeps_the_first_source_when_two_share_a_type(): void
    {
        $log = Log::spy();

        $this->registry->register(FakeLinkableRecordSource::class, 'example-module');
        $this->registry->register(SameTypeFakeLinkableRecordSource::class, 'another-module');

        $source = $this->registry->source('fake');

        $this->assertInstanceOf(FakeLinkableRecordSource::class, $source);
        $this->assertNotInstanceOf(SameTypeFakeLinkableRecordSource::class, $source);
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function the_registry_is_a_container_singleton(): void
    {
        $registry = $this->app->make(LinkableRecordSourceRegistryInterface::class);

        $this->assertSame($registry, $this->app->make(LinkableRecordSourceRegistryInterface::class));
    }
}
