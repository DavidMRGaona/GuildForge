<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Linking\Services;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Infrastructure\Linking\Services\LinkableRecordLookup;
use App\Infrastructure\Linking\Services\LinkableRecordSourceRegistry;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Linking\FakeLinkableRecordSource;
use Tests\Support\Linking\OtherFakeLinkableRecordSource;
use Tests\Support\Linking\ThrowingLinkableRecordSource;
use Tests\TestCase;

final class LinkableRecordLookupTest extends TestCase
{
    private const string VIEWER = '0195f1a4-0000-7000-8000-000000000001';

    private LinkableRecordSourceRegistry $registry;

    private LinkableRecordLookup $lookup;

    protected function setUp(): void
    {
        parent::setUp();

        FakeLinkableRecordSource::$records = [];
        $this->registry = new LinkableRecordSourceRegistry($this->app);
        $this->lookup = new LinkableRecordLookup($this->registry);
    }

    protected function tearDown(): void
    {
        FakeLinkableRecordSource::$records = [];

        parent::tearDown();
    }

    #[Test]
    public function it_finds_a_record_through_the_source_of_its_type(): void
    {
        $this->registry->register(FakeLinkableRecordSource::class, 'example-module');
        FakeLinkableRecordSource::$records = [$this->record('fake', 'one')];

        $record = $this->lookup->find('fake', 'one', self::VIEWER);

        $this->assertSame('one', $record?->id);
        $this->assertNull($this->lookup->find('fake', 'missing', self::VIEWER));
    }

    #[Test]
    public function a_type_without_a_source_shows_nothing_and_is_not_an_error(): void
    {
        $log = Log::spy();

        $this->assertNull($this->lookup->find('fake', 'one', self::VIEWER));
        $this->assertSame([], $this->lookup->search('fake', 'anything', self::VIEWER));
        $log->shouldNotHaveReceived('error');
    }

    #[Test]
    public function a_source_that_throws_shows_nothing_and_logs_an_error(): void
    {
        $log = Log::spy();
        $this->registry->register(ThrowingLinkableRecordSource::class, 'broken-module');

        $this->assertNull($this->lookup->find('throwing', 'one', self::VIEWER));
        $this->assertSame([], $this->lookup->search('throwing', 'anything', self::VIEWER));
        $log->shouldHaveReceived('error')->twice();
    }

    #[Test]
    public function a_record_answered_for_another_type_or_id_is_not_shown(): void
    {
        $log = Log::spy();
        $this->registry->register(OtherFakeLinkableRecordSource::class, 'example-module');
        FakeLinkableRecordSource::$records = [$this->record('fake', 'one')];

        // The source answers with a 'fake' record although it offers 'other-fake'
        $this->assertNull($this->lookup->find('other-fake', 'one', self::VIEWER));
        $log->shouldHaveReceived('error')->once();
    }

    #[Test]
    public function search_keeps_only_records_of_the_type_up_to_the_limit(): void
    {
        $log = Log::spy();
        $this->registry->register(FakeLinkableRecordSource::class, 'example-module');
        FakeLinkableRecordSource::$records = [
            $this->record('fake', 'one'),
            $this->record('other-fake', 'stray'),
            $this->record('fake', 'two'),
            $this->record('fake', 'three'),
        ];

        $records = $this->lookup->search('fake', 'anything', self::VIEWER, 3);

        $this->assertSame(['one', 'two'], array_map(fn (LinkableRecordDTO $record): string => $record->id, $records));
        $log->shouldHaveReceived('error')->once();
    }

    #[Test]
    public function search_asks_at_most_the_largest_limit_and_nothing_below_one(): void
    {
        $this->registry->register(FakeLinkableRecordSource::class, 'example-module');
        FakeLinkableRecordSource::$records = array_map(
            fn (int $index): LinkableRecordDTO => $this->record('fake', "record-{$index}"),
            range(1, LinkableRecordLookupInterface::MAX_SEARCH_LIMIT + 5),
        );

        $this->assertCount(LinkableRecordLookupInterface::MAX_SEARCH_LIMIT, $this->lookup->search('fake', '', self::VIEWER, 500));
        $this->assertSame([], $this->lookup->search('fake', '', self::VIEWER, 0));
    }

    #[Test]
    public function types_lists_each_readable_source_with_its_label(): void
    {
        $log = Log::spy();
        $this->registry->registerMany([
            FakeLinkableRecordSource::class,
            ThrowingLinkableRecordSource::class,
            OtherFakeLinkableRecordSource::class,
        ], 'example-module');

        $this->assertSame(['fake' => 'Fake record', 'other-fake' => 'Fake record'], $this->lookup->types());
        $log->shouldHaveReceived('error')->once();
    }

    #[Test]
    public function the_lookup_is_a_container_singleton(): void
    {
        $lookup = $this->app->make(LinkableRecordLookupInterface::class);

        $this->assertSame($lookup, $this->app->make(LinkableRecordLookupInterface::class));
    }

    private function record(string $type, string $id): LinkableRecordDTO
    {
        return new LinkableRecordDTO(type: $type, id: $id, title: "Record {$id}");
    }
}
