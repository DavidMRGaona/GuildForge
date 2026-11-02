<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Knowledge;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Infrastructure\Knowledge\Search\MeilisearchHitMapper;
use PHPUnit\Framework\TestCase;
use Tests\Support\Knowledge\KnowledgeGuides;

final class MeilisearchHitMapperTest extends TestCase
{
    public function test_hits_outside_candidates_are_discarded(): void
    {
        $candidates = [KnowledgeGuides::make('core.visible', title: 'Visible')];
        $raw = ['hits' => [
            ['guide_id' => 'core.secret', '_formatted' => ['body_text' => "\u{E000}secreto\u{E001}"]],
            ['guide_id' => 'core.visible', '_formatted' => ['body_text' => 'texto']],
            ['_formatted' => []],
            'not a hit',
        ]];

        $hits = (new MeilisearchHitMapper)->map($raw, $candidates, 10);

        $this->assertSame(['core.visible'], array_map(static fn (KnowledgeSearchHitDTO $hit): string => $hit->id, $hits));
    }

    public function test_title_and_category_come_from_the_candidate(): void
    {
        $candidates = [KnowledgeGuides::make('core.visible', title: 'Título real', categoryId: 'account')];
        $raw = ['hits' => [['guide_id' => 'core.visible', 'title' => 'Otro', '_formatted' => ['body_text' => 'x']]]];

        $hit = (new MeilisearchHitMapper)->map($raw, $candidates, 10)[0];

        $this->assertSame('Título real', $hit->title);
        $this->assertSame('account', $hit->categoryId);
    }

    public function test_highlight_tags_become_segments_and_markup_stays_text(): void
    {
        $candidates = [KnowledgeGuides::make('core.visible')];
        $raw = ['hits' => [['guide_id' => 'core.visible', '_formatted' => [
            'body_text' => "…usa <b>esto</b> para la \u{E000}inscripción\u{E001} y\n\nla \u{E000}mesa\u{E001}",
        ]]]];

        $hit = (new MeilisearchHitMapper)->map($raw, $candidates, 10)[0];

        $this->assertSame([
            ['text' => '…usa <b>esto</b> para la ', 'match' => false],
            ['text' => 'inscripción', 'match' => true],
            ['text' => ' y la ', 'match' => false],
            ['text' => 'mesa', 'match' => true],
        ], $hit->snippet);
    }

    public function test_the_summary_is_used_when_only_it_has_a_match(): void
    {
        $candidates = [KnowledgeGuides::make('core.visible')];
        $raw = ['hits' => [['guide_id' => 'core.visible', '_formatted' => [
            'body_text' => 'Nada que ver…',
            'summary' => "Sobre la \u{E000}cuota\u{E001}",
        ]]]];

        $hit = (new MeilisearchHitMapper)->map($raw, $candidates, 10)[0];

        $this->assertSame([['text' => 'Sobre la ', 'match' => false], ['text' => 'cuota', 'match' => true]], $hit->snippet);
    }

    public function test_duplicates_and_the_limit_are_respected(): void
    {
        $candidates = [KnowledgeGuides::make('core.a'), KnowledgeGuides::make('core.b')];
        $raw = ['hits' => [
            ['guide_id' => 'core.a', '_formatted' => []],
            ['guide_id' => 'core.a', '_formatted' => []],
            ['guide_id' => 'core.b', '_formatted' => []],
        ]];

        $this->assertCount(2, (new MeilisearchHitMapper)->map($raw, $candidates, 10));
        $this->assertCount(1, (new MeilisearchHitMapper)->map($raw, $candidates, 1));
    }

    public function test_an_unclosed_highlight_is_still_handled(): void
    {
        $candidates = [KnowledgeGuides::make('core.a')];
        $raw = ['hits' => [['guide_id' => 'core.a', '_formatted' => ['body_text' => "a \u{E000}b"]]]];

        $this->assertSame(
            [['text' => 'a ', 'match' => false], ['text' => 'b', 'match' => true]],
            (new MeilisearchHitMapper)->map($raw, $candidates, 10)[0]->snippet,
        );
    }
}
