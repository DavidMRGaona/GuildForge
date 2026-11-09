<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Publications\Services;

use App\Application\Publications\Contracts\PublicationRosterInterface;
use App\Application\Publications\Services\PublicationSourceRegistryInterface;
use App\Infrastructure\Publications\Services\PublicationSourceRegistry;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Support\Publications\FakePublicationSource;
use Tests\Support\Publications\FakeRosterPublicationSource;
use Tests\Support\Publications\InvalidTypePublicationSource;
use Tests\Support\Publications\OtherFakePublicationSource;
use Tests\Support\Publications\SameTypeFakePublicationSource;
use Tests\Support\Publications\ThrowingTypePublicationSource;
use Tests\Support\Publications\UnresolvablePublicationSource;
use Tests\TestCase;

final class PublicationSourceRegistryTest extends TestCase
{
    private PublicationSourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PublicationSourceRegistry($this->app);
    }

    #[Test]
    public function it_can_register_a_source_class(): void
    {
        $this->registry->register(FakePublicationSource::class);

        $this->assertSame([FakePublicationSource::class], $this->registry->all());
    }

    #[Test]
    public function it_can_register_many_source_classes_with_a_module(): void
    {
        $this->registry->registerMany([
            FakePublicationSource::class,
            OtherFakePublicationSource::class,
        ], 'example-module');

        $this->assertSame(
            [FakePublicationSource::class, OtherFakePublicationSource::class],
            $this->registry->all(),
        );
    }

    #[Test]
    public function it_does_not_duplicate_a_class_registered_twice(): void
    {
        $this->registry->register(FakePublicationSource::class);
        $this->registry->register(FakePublicationSource::class);

        $this->assertCount(1, $this->registry->all());
    }

    #[Test]
    public function it_can_unregister_module_source_classes(): void
    {
        $this->registry->registerMany([FakePublicationSource::class], 'example-module');
        $this->registry->registerMany([OtherFakePublicationSource::class], 'another-module');

        $this->registry->unregisterModule('example-module');

        $this->assertSame([OtherFakePublicationSource::class], $this->registry->all());
    }

    #[Test]
    public function it_can_clear_all_source_classes(): void
    {
        $this->registry->registerMany([
            FakePublicationSource::class,
            OtherFakePublicationSource::class,
        ], 'example-module');

        $this->registry->clear();

        $this->assertSame([], $this->registry->all());
        $this->assertSame([], $this->registry->sources());
    }

    #[Test]
    public function it_resolves_sources_keyed_by_type(): void
    {
        $this->registry->registerMany([
            FakePublicationSource::class,
            OtherFakePublicationSource::class,
        ], 'example-module');

        $sources = $this->registry->sources();

        $this->assertSame(['fake', 'other-fake'], array_keys($sources));
        $this->assertInstanceOf(FakePublicationSource::class, $sources['fake']);
        $this->assertInstanceOf(OtherFakePublicationSource::class, $sources['other-fake']);
    }

    #[Test]
    public function a_source_offering_its_roster_is_told_apart_with_instanceof(): void
    {
        $this->registry->registerMany([FakePublicationSource::class, FakeRosterPublicationSource::class]);

        $sources = $this->registry->sources();

        $this->assertNotInstanceOf(PublicationRosterInterface::class, $sources['fake']);
        $this->assertInstanceOf(PublicationRosterInterface::class, $this->registry->source('fake-roster'));
    }

    #[Test]
    public function it_finds_a_source_by_type(): void
    {
        $this->registry->registerMany([
            FakePublicationSource::class,
            OtherFakePublicationSource::class,
        ], 'example-module');

        $this->assertInstanceOf(OtherFakePublicationSource::class, $this->registry->source('other-fake'));
        $this->assertNull($this->registry->source('unknown'));
    }

    #[Test]
    public function it_skips_a_class_that_cannot_be_resolved(): void
    {
        $log = Log::spy();

        $this->registry->register(UnresolvablePublicationSource::class, 'broken-module');
        $this->registry->register(FakePublicationSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_skips_a_class_that_is_not_a_publication_source(): void
    {
        $log = Log::spy();

        $this->registry->register(stdClass::class, 'broken-module');
        $this->registry->register(FakePublicationSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $this->assertNull($this->registry->source('stdClass'));
        $log->shouldHaveReceived('warning')->twice();
    }

    #[Test]
    public function it_skips_a_source_whose_type_cannot_be_read(): void
    {
        $log = Log::spy();

        $this->registry->register(ThrowingTypePublicationSource::class, 'broken-module');
        $this->registry->register(FakePublicationSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_skips_a_source_whose_type_is_not_kebab_case(): void
    {
        $log = Log::spy();

        $this->registry->register(InvalidTypePublicationSource::class, 'broken-module');
        $this->registry->register(FakePublicationSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_keeps_the_first_source_when_two_share_a_type(): void
    {
        $log = Log::spy();

        $this->registry->register(FakePublicationSource::class, 'example-module');
        $this->registry->register(SameTypeFakePublicationSource::class, 'another-module');

        $source = $this->registry->source('fake');

        $this->assertInstanceOf(FakePublicationSource::class, $source);
        $this->assertNotInstanceOf(SameTypeFakePublicationSource::class, $source);
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function the_registry_is_a_container_singleton(): void
    {
        $registry = $this->app->make(PublicationSourceRegistryInterface::class);

        $this->assertSame($registry, $this->app->make(PublicationSourceRegistryInterface::class));
    }
}
