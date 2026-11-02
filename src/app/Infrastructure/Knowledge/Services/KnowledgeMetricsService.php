<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Text\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class KnowledgeMetricsService implements KnowledgeMetricsServiceInterface
{
    private const int MAX_QUERY_LENGTH = 100;

    private const int MIN_QUERY_LENGTH = 2;

    private const int PHONE_DIGITS = 7;

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
        $normalized = $this->normalize($query);
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

    private function normalize(string $query): ?string
    {
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', TextNormalizer::normalize($query)));

        if (str_contains($collapsed, '@')) {
            return null;
        }

        if (preg_match_all('/\d/', $collapsed) >= self::PHONE_DIGITS) {
            return null;
        }

        $trimmed = trim(mb_substr($collapsed, 0, self::MAX_QUERY_LENGTH));

        return mb_strlen($trimmed) < self::MIN_QUERY_LENGTH ? null : $trimmed;
    }
}
