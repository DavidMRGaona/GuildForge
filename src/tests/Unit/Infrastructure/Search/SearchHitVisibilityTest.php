<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Search;

use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Search\CheckedSearchRows;
use App\Infrastructure\Search\Search\SearchHitVisibility;
use RuntimeException;
use stdClass;
use Tests\Support\Logging\RecordingLogger;
use Tests\Support\Search\CheckingAndWithholdingSearchSource;
use Tests\Support\Search\CheckingSearchSource;
use Tests\Support\Search\InMemoryIndexedSearchSource;
use Tests\Support\Search\SnippetWithholdingSearchSource;
use Tests\TestCase;

final class SearchHitVisibilityTest extends TestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new RecordingLogger;
        $registry = $this->app->make(SearchSourceRegistryInterface::class);
        $registry->clear();
        $this->app->instance(InMemoryIndexedSearchSource::class, new InMemoryIndexedSearchSource('events'));
        $registry->register(InMemoryIndexedSearchSource::class);
    }

    public function test_rows_of_a_source_without_the_check_are_kept(): void
    {
        $rows = ['a' => $this->row('events', 'e1'), 'b' => $this->row('events', 'e2')];

        $checked = $this->check($rows);

        $this->assertSame($rows, $checked->rows);
        $this->assertSame([], $checked->dropped);
        $this->assertSame([], $this->logger->records);
    }

    public function test_rows_the_source_no_longer_shows_are_dropped_keeping_order_and_keys(): void
    {
        $source = $this->checking(CheckingSearchSource::allowing('notes', ['n1', 'n3']));
        $rows = [
            'a' => $this->row('notes', 'n1'),
            'b' => $this->row('events', 'e1'),
            'c' => $this->row('notes', 'n2'),
            'd' => $this->row('notes', 'n3'),
        ];
        $user = new stdClass;

        $checked = $this->check($rows, $user);

        $this->assertSame(['a', 'b', 'd'], array_keys($checked->rows));
        $this->assertSame(['notes' => 1], $checked->dropped);
        $this->assertSame(1, $checked->droppedTotal());
        // One call for the whole page, with the person searching
        $this->assertCount(1, $source->checks);
        $this->assertSame(['n1', 'n2', 'n3'], $source->checks[0]['ids']);
        $this->assertSame($user, $source->checks[0]['user']);
    }

    public function test_repeated_ids_are_asked_once(): void
    {
        $source = $this->checking(CheckingSearchSource::allowing('notes', ['n1']));

        $checked = $this->check(['a' => $this->row('notes', 'n1'), 'b' => $this->row('notes', 'n1')]);

        $this->assertSame(['n1'], $source->checks[0]['ids']);
        $this->assertCount(2, $checked->rows);
    }

    public function test_ids_not_asked_for_are_ignored(): void
    {
        $this->checking(new CheckingSearchSource('notes', static fn (array $ids): array => ['n9', 'n2', 42, null]));

        $checked = $this->check(['a' => $this->row('notes', 'n1'), 'b' => $this->row('notes', 'n2')]);

        $this->assertSame(['b'], array_keys($checked->rows));
        $this->assertSame(['notes' => 1], $checked->dropped);
    }

    public function test_a_check_that_throws_drops_every_row_of_its_type_and_logs_an_error(): void
    {
        $this->checking(new CheckingSearchSource('notes', static fn (): never => throw new RuntimeException('Database is gone')));

        $checked = $this->check([
            'a' => $this->row('notes', 'n1'),
            'b' => $this->row('events', 'e1'),
            'c' => $this->row('notes', 'n2'),
        ]);

        $this->assertSame(['b'], array_keys($checked->rows));
        $this->assertSame(['notes' => 2], $checked->dropped);
        $this->assertCount(1, $this->logger->records);
        $this->assertSame('error', $this->logger->records[0]['level']);
        $this->assertStringContainsString('Database is gone', $this->logger->records[0]['message']);
        $this->assertSame('notes', $this->logger->records[0]['context']['type'] ?? null);
    }

    public function test_an_answer_that_is_no_list_fails_like_an_error(): void
    {
        $this->checking(new CheckingSearchSource('notes', static fn (): string => 'n1'));

        $checked = $this->check(['a' => $this->row('notes', 'n1')]);

        $this->assertSame([], $checked->rows);
        $this->assertSame(['notes' => 1], $checked->dropped);
        $this->assertSame('error', $this->logger->records[0]['level'] ?? null);
    }

    public function test_no_rows_ask_nothing(): void
    {
        $source = $this->checking(CheckingSearchSource::allowing('notes', []));

        $checked = $this->check([]);

        $this->assertSame([], $checked->rows);
        $this->assertSame([], $source->checks);
    }

    public function test_adjust_takes_dropped_rows_from_their_type_and_removes_empty_types(): void
    {
        $checked = new CheckedSearchRows([], ['notes' => 2, 'events' => 1, 'unknown' => 3]);

        $this->assertSame(['notes' => 3, 'help' => 2], $checked->adjust(['notes' => 5, 'events' => 1, 'help' => 2]));
        $this->assertSame(6, $checked->droppedTotal());
    }

    public function test_without_a_withholding_source_every_kept_row_shows_its_snippet(): void
    {
        $this->checking(CheckingSearchSource::allowing('notes', ['n1']));
        $rows = ['a' => $this->row('notes', 'n1'), 'b' => $this->row('events', 'e1')];

        $checked = $this->check($rows);

        $this->assertFalse($checked->withholdsSnippet($rows['a']));
        $this->assertFalse($checked->withholdsSnippet($rows['b']));
    }

    public function test_a_source_withholds_the_stored_snippet_of_rows_that_stay_visible(): void
    {
        $source = $this->withholding(SnippetWithholdingSearchSource::withholding('notes', ['n2']));
        $rows = [
            'a' => $this->row('notes', 'n1'),
            'b' => $this->row('notes', 'n2'),
            'c' => $this->row('events', 'n2'),
        ];
        $user = new stdClass;

        $checked = $this->check($rows, $user);

        // The row stays, only its snippet goes; the same id of another type is not touched
        $this->assertSame(['a', 'b', 'c'], array_keys($checked->rows));
        $this->assertSame([], $checked->dropped);
        $this->assertFalse($checked->withholdsSnippet($rows['a']));
        $this->assertTrue($checked->withholdsSnippet($rows['b']));
        $this->assertFalse($checked->withholdsSnippet($rows['c']));
        $this->assertCount(1, $source->calls);
        $this->assertSame(['n1', 'n2'], $source->calls[0]['ids']);
        $this->assertSame($user, $source->calls[0]['user']);
    }

    public function test_only_rows_still_visible_are_asked_about_their_snippet(): void
    {
        $source = $this->both(new CheckingAndWithholdingSearchSource('notes', visible: ['n1', 'n3'], stale: ['n3']));
        $rows = ['a' => $this->row('notes', 'n1'), 'b' => $this->row('notes', 'n2'), 'c' => $this->row('notes', 'n3')];

        $checked = $this->check($rows);

        $this->assertSame(['a', 'c'], array_keys($checked->rows));
        $this->assertSame([['n1', 'n2', 'n3']], $source->visibilityCalls);
        $this->assertSame([['n1', 'n3']], $source->snippetCalls);
        $this->assertFalse($checked->withholdsSnippet($rows['a']));
        $this->assertTrue($checked->withholdsSnippet($rows['c']));
    }

    public function test_a_page_where_no_row_is_left_asks_nothing_about_snippets(): void
    {
        $source = $this->both(new CheckingAndWithholdingSearchSource('notes', visible: []));

        $checked = $this->check(['a' => $this->row('notes', 'n1')]);

        $this->assertSame([], $checked->rows);
        $this->assertSame([], $source->snippetCalls);
    }

    public function test_ids_not_asked_for_withhold_nothing(): void
    {
        $this->withholding(new SnippetWithholdingSearchSource('notes', static fn (): array => ['n9', 42, null]));
        $rows = ['a' => $this->row('notes', 'n1')];

        $checked = $this->check($rows);

        $this->assertFalse($checked->withholdsSnippet($rows['a']));
        $this->assertSame([], $this->logger->records);
    }

    public function test_a_withholding_check_that_throws_withholds_every_snippet_of_its_type_and_logs_an_error(): void
    {
        $this->withholding(new SnippetWithholdingSearchSource('notes', static fn (): never => throw new RuntimeException('Database is gone')));
        $rows = ['a' => $this->row('notes', 'n1'), 'b' => $this->row('events', 'e1'), 'c' => $this->row('notes', 'n2')];

        $checked = $this->check($rows);

        // Fails closed without hiding the results: they show the title, never the stored text
        $this->assertSame(['a', 'b', 'c'], array_keys($checked->rows));
        $this->assertTrue($checked->withholdsSnippet($rows['a']));
        $this->assertFalse($checked->withholdsSnippet($rows['b']));
        $this->assertTrue($checked->withholdsSnippet($rows['c']));
        $this->assertCount(1, $this->logger->records);
        $this->assertSame('error', $this->logger->records[0]['level']);
        $this->assertStringContainsString('Database is gone', $this->logger->records[0]['message']);
        $this->assertSame('notes', $this->logger->records[0]['context']['type'] ?? null);
    }

    public function test_a_withholding_answer_that_is_no_list_fails_like_an_error(): void
    {
        $this->withholding(new SnippetWithholdingSearchSource('notes', static fn (): string => 'n1'));
        $rows = ['a' => $this->row('notes', 'n1')];

        $checked = $this->check($rows);

        $this->assertTrue($checked->withholdsSnippet($rows['a']));
        $this->assertSame('error', $this->logger->records[0]['level'] ?? null);
    }

    private function withholding(SnippetWithholdingSearchSource $source): SnippetWithholdingSearchSource
    {
        $this->app->instance(SnippetWithholdingSearchSource::class, $source);
        $this->app->make(SearchSourceRegistryInterface::class)->register(SnippetWithholdingSearchSource::class);

        return $source;
    }

    private function both(CheckingAndWithholdingSearchSource $source): CheckingAndWithholdingSearchSource
    {
        $this->app->instance(CheckingAndWithholdingSearchSource::class, $source);
        $this->app->make(SearchSourceRegistryInterface::class)->register(CheckingAndWithholdingSearchSource::class);

        return $source;
    }

    private function checking(CheckingSearchSource $source): CheckingSearchSource
    {
        $this->app->instance(CheckingSearchSource::class, $source);
        $this->app->make(SearchSourceRegistryInterface::class)->register(CheckingSearchSource::class);

        return $source;
    }

    /**
     * @param  array<string, SearchDocumentModel>  $rows
     * @return CheckedSearchRows<string>
     */
    private function check(array $rows, ?object $user = null): CheckedSearchRows
    {
        return (new SearchHitVisibility($this->app->make(SearchSourceRegistryInterface::class), $this->logger))->check($rows, $user);
    }

    private function row(string $type, string $sourceId): SearchDocumentModel
    {
        return new SearchDocumentModel(['type' => $type, 'source_id' => $sourceId]);
    }
}
