<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Metrics\AnonymousQueryNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class KnowledgeMetricsService implements KnowledgeMetricsServiceInterface
{
    public function __construct(private TimezoneServiceInterface $timezone) {}

    public function recordVote(GuideId $id, bool $helpful): void
    {
        DB::table('knowledge_guide_feedback')->upsert(
            [[
                'id' => (string) Str::uuid(),
                'guide_id' => $id->value(),
                'day' => $this->day(),
                'helpful_count' => $helpful ? 1 : 0,
                'not_helpful_count' => $helpful ? 0 : 1,
            ]],
            ['guide_id', 'day'],
            [
                $helpful ? 'helpful_count' : 'not_helpful_count' => DB::raw(
                    'knowledge_guide_feedback.'.($helpful ? 'helpful_count' : 'not_helpful_count').' + 1'
                ),
            ],
        );
    }

    public function recordMiss(string $query, GuideAudience $audience): void
    {
        $normalized = AnonymousQueryNormalizer::normalize($query);
        if ($normalized === null) {
            return;
        }

        DB::table('knowledge_search_misses')->upsert(
            [[
                'id' => (string) Str::uuid(),
                'normalized_query' => $normalized,
                'audience' => $audience->value,
                'day' => $this->day(),
                'count' => 1,
            ]],
            ['normalized_query', 'audience', 'day'],
            ['count' => DB::raw('knowledge_search_misses.count + 1')],
        );
    }

    public function feedbackTotals(int $days): array
    {
        $rows = DB::table('knowledge_guide_feedback')
            ->selectRaw('guide_id, SUM(helpful_count) as helpful, SUM(not_helpful_count) as not_helpful')
            ->where('day', '>', $this->dayOffset(-max(0, $days)))
            ->groupBy('guide_id')
            ->orderBy('guide_id')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(string) $row->guide_id] = ['helpful' => (int) $row->helpful, 'notHelpful' => (int) $row->not_helpful];
        }

        return $totals;
    }

    public function topMisses(int $days, int $limit): array
    {
        $rows = DB::table('knowledge_search_misses')
            ->selectRaw('normalized_query, audience, SUM(count) as total')
            ->where('day', '>', $this->dayOffset(-max(0, $days)))
            ->groupBy('normalized_query', 'audience')
            ->orderByDesc('total')
            ->orderBy('normalized_query')
            ->orderBy('audience')
            ->limit(max(0, $limit))
            ->get();

        return array_values(array_map(
            static fn (object $row): array => ['query' => (string) $row->normalized_query, 'audience' => (string) $row->audience, 'count' => (int) $row->total],
            $rows->all(),
        ));
    }

    public function prune(int $days): int
    {
        $limit = $this->timezone->today()->modify('-'.max(0, $days).' days')->format('Y-m-d');

        return DB::table('knowledge_guide_feedback')->where('day', '<', $limit)->delete()
            + DB::table('knowledge_search_misses')->where('day', '<', $limit)->delete();
    }

    private function day(): string
    {
        return $this->timezone->today()->format('Y-m-d');
    }

    private function dayOffset(int $days): string
    {
        return $this->timezone->today()->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }
}
