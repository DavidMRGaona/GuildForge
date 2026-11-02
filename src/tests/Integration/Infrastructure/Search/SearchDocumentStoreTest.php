<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class SearchDocumentStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SearchDocumentStoreInterface $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->app->make(SearchDocumentStoreInterface::class);
    }

    private function document(string $id, string $title = 'Torneo de primavera'): SearchDocumentDTO
    {
        return new SearchDocumentDTO(id: $id, title: $title, body: 'Cuerpo', url: '/eventos/'.$id);
    }

    public function test_upsert_creates_then_updates_same_row(): void
    {
        $first = $this->store->upsert('event', null, $this->document('a'));
        $second = $this->store->upsert('event', null, $this->document('a', 'Otro título'));

        $this->assertSame($first, $second);
        $this->assertSame(1, SearchDocumentModel::query()->count());
        $this->assertSame('Otro título', SearchDocumentModel::query()->findOrFail($first)->title);
    }

    public function test_delete_returns_removed_id(): void
    {
        $id = $this->store->upsert('event', null, $this->document('a'));

        $this->assertSame($id, $this->store->delete('event', 'a'));
        $this->assertNull($this->store->delete('event', 'a'));
        $this->assertSame(0, SearchDocumentModel::query()->count());
    }

    public function test_delete_except_keeps_listed_ids(): void
    {
        $this->store->upsert('event', null, $this->document('a'));
        $removed = $this->store->upsert('event', null, $this->document('b'));
        $this->store->upsert('event', null, $this->document('c'));

        $this->assertSame([$removed], $this->store->deleteExcept('event', ['a', 'c']));
        $this->assertSame(['a', 'c'], SearchDocumentModel::query()->orderBy('source_id')->pluck('source_id')->all());
    }

    public function test_delete_type_only_touches_that_type(): void
    {
        $event = $this->store->upsert('event', null, $this->document('a'));
        $this->store->upsert('article', null, $this->document('a'));

        $this->assertSame([$event], $this->store->deleteType('event'));
        $this->assertSame(['article'], SearchDocumentModel::query()->pluck('type')->all());
    }

    public function test_upsert_maps_the_document_fields(): void
    {
        $doc = new SearchDocumentDTO(
            id: 'x',
            title: 'T',
            body: 'B',
            url: '/x',
            keywords: 'k',
            meta: 'm',
            imagePublicId: 'img',
            startsAt: new DateTimeImmutable('2026-12-01 10:00:00'),
            finished: true,
            audience: SearchAudience::Public,
        );

        $id = $this->store->upsert('game_table', 'game-tables', $doc);
        $row = SearchDocumentModel::query()->findOrFail($id);

        $this->assertSame('game-tables', $row->module);
        $this->assertSame($this->app->getLocale(), $row->locale);
        $this->assertTrue($row->finished);
        $this->assertSame('img', $row->image_public_id);
        $this->assertNotNull($row->starts_at);
    }

    public function test_searchable_array_uses_end_or_start_as_timestamp(): void
    {
        $withEnd = $this->store->upsert('event', null, new SearchDocumentDTO(
            id: 'e', title: 'T', body: 'B', url: '/e',
            startsAt: new DateTimeImmutable('2026-12-01 10:00:00'),
            endsAt: new DateTimeImmutable('2026-12-02 10:00:00'),
        ));
        $startOnly = $this->store->upsert('event', null, new SearchDocumentDTO(
            id: 's', title: 'T', body: 'B', url: '/s',
            startsAt: new DateTimeImmutable('2026-12-01 10:00:00'),
        ));
        $neither = $this->store->upsert('article', null, $this->document('n'));

        $end = SearchDocumentModel::query()->findOrFail($withEnd);
        $start = SearchDocumentModel::query()->findOrFail($startOnly);
        $none = SearchDocumentModel::query()->findOrFail($neither);

        $this->assertSame($end->ends_at?->getTimestamp(), $end->toSearchableArray()['ends_at_ts']);
        $this->assertSame($start->starts_at?->getTimestamp(), $start->toSearchableArray()['ends_at_ts']);
        $this->assertGreaterThan($start->toSearchableArray()['ends_at_ts'], $end->toSearchableArray()['ends_at_ts']);
        $this->assertNull($none->toSearchableArray()['ends_at_ts']);
        $this->assertArrayNotHasKey('url', $end->toSearchableArray());
    }
}
