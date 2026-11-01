<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class KnowledgeMetricsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private KnowledgeMetricsServiceInterface $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metrics = $this->app->make(KnowledgeMetricsServiceInterface::class);
    }

    public function test_votes_accumulate_per_guide_and_day(): void
    {
        $id = GuideId::fromString('core.signup');
        $this->metrics->recordVote($id, true);
        $this->metrics->recordVote($id, true);
        $this->metrics->recordVote($id, false);
        $this->metrics->recordVote(GuideId::fromString('core.other'), true);

        $row = DB::table('knowledge_guide_feedback')->where('guide_id', 'core.signup')->sole();
        $this->assertSame(2, (int) $row->helpful_count);
        $this->assertSame(1, (int) $row->not_helpful_count);
        $this->assertSame($this->today(), substr((string) $row->day, 0, 10));
        $this->assertSame(2, DB::table('knowledge_guide_feedback')->count());
    }

    public function test_misses_are_normalized_and_counted(): void
    {
        $this->metrics->recordMiss('Inscripción ', GuideAudience::Site);
        $this->metrics->recordMiss('  inscripcion', GuideAudience::Site);
        $this->metrics->recordMiss('inscripcion', GuideAudience::Panel);
        $this->metrics->recordMiss("la   cuota\tde  socio", GuideAudience::Site);

        $row = DB::table('knowledge_search_misses')->where('normalized_query', 'inscripcion')->where('audience', 'site')->sole();
        $this->assertSame(2, (int) $row->count);
        $this->assertSame(1, DB::table('knowledge_search_misses')->where('audience', 'panel')->count());
        $this->assertSame(1, DB::table('knowledge_search_misses')->where('normalized_query', 'la cuota de socio')->count());
    }

    public function test_misses_that_look_like_personal_data_are_dropped(): void
    {
        foreach (['ana@x.es', '612345678', '612 345 678', 'telefono 6123456', 'a', ' ', ''] as $query) {
            $this->metrics->recordMiss($query, GuideAudience::Site);
        }

        $this->assertSame(0, DB::table('knowledge_search_misses')->count());

        $this->metrics->recordMiss('mesa 123456', GuideAudience::Site);
        $this->assertSame(1, DB::table('knowledge_search_misses')->count());
    }

    public function test_long_misses_are_trimmed_to_100_characters(): void
    {
        $this->metrics->recordMiss(str_repeat('á', 150), GuideAudience::Site);

        $this->assertSame(str_repeat('a', 100), DB::table('knowledge_search_misses')->value('normalized_query'));
    }

    public function test_prune_removes_rows_older_than_the_retention(): void
    {
        DB::table('knowledge_guide_feedback')->insert([
            ['id' => '00000000-0000-4000-8000-000000000071', 'guide_id' => 'core.a', 'day' => $this->daysAgo(200), 'helpful_count' => 1, 'not_helpful_count' => 0],
            ['id' => '00000000-0000-4000-8000-000000000072', 'guide_id' => 'core.a', 'day' => $this->daysAgo(10), 'helpful_count' => 1, 'not_helpful_count' => 0],
        ]);
        DB::table('knowledge_search_misses')->insert([
            ['id' => '00000000-0000-4000-8000-000000000081', 'normalized_query' => 'old', 'audience' => 'site', 'day' => $this->daysAgo(181), 'count' => 1],
            ['id' => '00000000-0000-4000-8000-000000000082', 'normalized_query' => 'edge', 'audience' => 'site', 'day' => $this->daysAgo(180), 'count' => 1],
            ['id' => '00000000-0000-4000-8000-000000000083', 'normalized_query' => 'new', 'audience' => 'site', 'day' => $this->daysAgo(1), 'count' => 1],
        ]);

        $this->assertSame(2, $this->metrics->prune(180));

        $this->assertSame(['00000000-0000-4000-8000-000000000072'], DB::table('knowledge_guide_feedback')->pluck('id')->all());
        $this->assertEqualsCanonicalizing(['00000000-0000-4000-8000-000000000082', '00000000-0000-4000-8000-000000000083'], DB::table('knowledge_search_misses')->pluck('id')->all());
    }

    private function today(): string
    {
        return $this->app->make(TimezoneServiceInterface::class)->today()->format('Y-m-d');
    }

    private function daysAgo(int $days): string
    {
        return $this->app->make(TimezoneServiceInterface::class)->today()->modify("-{$days} days")->format('Y-m-d');
    }
}
