<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Search\Services\SearchMetricsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SearchMetricsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SearchMetricsServiceInterface $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metrics = $this->app->make(SearchMetricsServiceInterface::class);
    }

    public function test_records_and_counts_misses_per_day(): void
    {
        $this->metrics->recordMiss('Inscripción ');
        $this->metrics->recordMiss('  inscripcion');
        $this->metrics->recordMiss('la   cuota de socio');

        $row = DB::table('search_misses')->where('normalized_query', 'inscripcion')->sole();
        $this->assertSame(2, (int) $row->count);
        $this->assertSame($this->daysAgo(0), substr((string) $row->day, 0, 10));
        $this->assertSame(2, DB::table('search_misses')->count());
    }

    public function test_blank_query_is_not_recorded(): void
    {
        foreach (['', ' ', 'a', 'ana@x.es', '612 345 678'] as $query) {
            $this->metrics->recordMiss($query);
        }

        $this->assertSame(0, DB::table('search_misses')->count());
    }

    public function test_top_misses_orders_by_count(): void
    {
        $this->insert('rare', 1, 1);
        $this->insert('common', 1, 5);
        $this->insert('common', 2, 3);
        $this->insert('middle', 3, 4);
        $this->insert('ancient', 40, 99);

        $top = $this->metrics->topMisses(30, 2);

        $this->assertSame([
            ['query' => 'common', 'count' => 8],
            ['query' => 'middle', 'count' => 4],
        ], $top);
    }

    public function test_prune_drops_old_days(): void
    {
        $this->insert('old', 181, 1);
        $this->insert('edge', 180, 1);
        $this->insert('new', 1, 1);

        $this->assertSame(1, $this->metrics->prune(180));

        $this->assertEqualsCanonicalizing(['edge', 'new'], DB::table('search_misses')->pluck('normalized_query')->all());
    }

    private function insert(string $query, int $daysAgo, int $count): void
    {
        DB::table('search_misses')->insert([
            'id' => (string) Str::uuid(),
            'normalized_query' => $query,
            'day' => $this->daysAgo($daysAgo),
            'count' => $count,
        ]);
    }

    private function daysAgo(int $days): string
    {
        return $this->app->make(TimezoneServiceInterface::class)->today()->modify("-{$days} days")->format('Y-m-d');
    }
}
