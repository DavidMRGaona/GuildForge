<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

use App\Application\Search\Services\SearchMetricsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Metrics\AnonymousQueryNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SearchMetricsService implements SearchMetricsServiceInterface
{
    public function __construct(private TimezoneServiceInterface $timezone) {}

    public function recordMiss(string $query): void
    {
        $normalized = AnonymousQueryNormalizer::normalize($query);
        if ($normalized === null) {
            return;
        }

        DB::table('search_misses')->upsert(
            [[
                'id' => (string) Str::uuid(),
                'normalized_query' => $normalized,
                'day' => $this->dayOffset(0),
                'count' => 1,
            ]],
            ['normalized_query', 'day'],
            ['count' => DB::raw('search_misses.count + 1')],
        );
    }

    public function topMisses(int $days, int $limit): array
    {
        $rows = DB::table('search_misses')
            ->selectRaw('normalized_query, SUM(count) as total')
            ->where('day', '>', $this->dayOffset(-max(0, $days)))
            ->groupBy('normalized_query')
            ->orderByDesc('total')
            ->orderBy('normalized_query')
            ->limit(max(0, $limit))
            ->get();

        return array_values(array_map(
            static fn (object $row): array => ['query' => (string) $row->normalized_query, 'count' => (int) $row->total],
            $rows->all(),
        ));
    }

    public function prune(int $days): int
    {
        return DB::table('search_misses')->where('day', '<', $this->dayOffset(-max(0, $days)))->delete();
    }

    private function dayOffset(int $days): string
    {
        return $this->timezone->today()->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }
}
