<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Surveys\Services;

use App\Application\Surveys\Services\SurveySubjectSourceRegistryInterface;
use App\Infrastructure\Surveys\Services\SurveySubjectSourceRegistry;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Support\Surveys\FakeSurveySubjectSource;
use Tests\Support\Surveys\OtherFakeSurveySubjectSource;
use Tests\Support\Surveys\SameTypeFakeSurveySubjectSource;
use Tests\Support\Surveys\ThrowingTypeSurveySubjectSource;
use Tests\Support\Surveys\UnresolvableSurveySubjectSource;
use Tests\TestCase;

final class SurveySubjectSourceRegistryTest extends TestCase
{
    private SurveySubjectSourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SurveySubjectSourceRegistry($this->app);
    }

    #[Test]
    public function it_can_register_a_source_class(): void
    {
        $this->registry->register(FakeSurveySubjectSource::class);

        $this->assertSame([FakeSurveySubjectSource::class], $this->registry->all());
    }

    #[Test]
    public function it_can_register_many_source_classes_with_a_module(): void
    {
        $this->registry->registerMany([
            FakeSurveySubjectSource::class,
            OtherFakeSurveySubjectSource::class,
        ], 'example-module');

        $this->assertSame(
            [FakeSurveySubjectSource::class, OtherFakeSurveySubjectSource::class],
            $this->registry->all(),
        );
    }

    #[Test]
    public function it_does_not_duplicate_a_class_registered_twice(): void
    {
        $this->registry->register(FakeSurveySubjectSource::class);
        $this->registry->register(FakeSurveySubjectSource::class);

        $this->assertCount(1, $this->registry->all());
    }

    #[Test]
    public function it_can_unregister_module_source_classes(): void
    {
        $this->registry->registerMany([FakeSurveySubjectSource::class], 'example-module');
        $this->registry->registerMany([OtherFakeSurveySubjectSource::class], 'another-module');

        $this->registry->unregisterModule('example-module');

        $this->assertSame([OtherFakeSurveySubjectSource::class], $this->registry->all());
    }

    #[Test]
    public function it_can_clear_all_source_classes(): void
    {
        $this->registry->registerMany([
            FakeSurveySubjectSource::class,
            OtherFakeSurveySubjectSource::class,
        ], 'example-module');

        $this->registry->clear();

        $this->assertSame([], $this->registry->all());
        $this->assertSame([], $this->registry->sources());
    }

    #[Test]
    public function it_resolves_sources_keyed_by_source_type(): void
    {
        $this->registry->registerMany([
            FakeSurveySubjectSource::class,
            OtherFakeSurveySubjectSource::class,
        ], 'example-module');

        $sources = $this->registry->sources();

        $this->assertSame(['fake', 'other-fake'], array_keys($sources));
        $this->assertInstanceOf(FakeSurveySubjectSource::class, $sources['fake']);
        $this->assertInstanceOf(OtherFakeSurveySubjectSource::class, $sources['other-fake']);
    }

    #[Test]
    public function it_finds_a_source_by_type(): void
    {
        $this->registry->registerMany([
            FakeSurveySubjectSource::class,
            OtherFakeSurveySubjectSource::class,
        ], 'example-module');

        $this->assertInstanceOf(OtherFakeSurveySubjectSource::class, $this->registry->source('other-fake'));
        $this->assertNull($this->registry->source('unknown'));
    }

    #[Test]
    public function it_skips_a_class_that_cannot_be_resolved(): void
    {
        $log = Log::spy();

        $this->registry->register(UnresolvableSurveySubjectSource::class, 'broken-module');
        $this->registry->register(FakeSurveySubjectSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_skips_a_class_that_is_not_a_survey_subject_source(): void
    {
        $log = Log::spy();

        $this->registry->register(stdClass::class, 'broken-module');
        $this->registry->register(FakeSurveySubjectSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $this->assertNull($this->registry->source('stdClass'));
        $log->shouldHaveReceived('warning')->twice();
    }

    #[Test]
    public function it_skips_a_source_whose_type_cannot_be_read(): void
    {
        $log = Log::spy();

        $this->registry->register(ThrowingTypeSurveySubjectSource::class, 'broken-module');
        $this->registry->register(FakeSurveySubjectSource::class, 'example-module');

        $this->assertSame(['fake'], array_keys($this->registry->sources()));
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function it_keeps_the_first_source_when_two_share_a_type(): void
    {
        $log = Log::spy();

        $this->registry->register(FakeSurveySubjectSource::class, 'example-module');
        $this->registry->register(SameTypeFakeSurveySubjectSource::class, 'another-module');

        $source = $this->registry->source('fake');

        $this->assertInstanceOf(FakeSurveySubjectSource::class, $source);
        $this->assertNotInstanceOf(SameTypeFakeSurveySubjectSource::class, $source);
        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function the_registry_is_a_container_singleton(): void
    {
        $registry = $this->app->make(SurveySubjectSourceRegistryInterface::class);

        $this->assertSame($registry, $this->app->make(SurveySubjectSourceRegistryInterface::class));
    }
}
