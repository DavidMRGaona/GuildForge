<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Activity;

use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Domain\Activity\ActivityRole;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActivitySubjectDTOsTest extends TestCase
{
    private const string ANA = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e5f';

    private const string LUIS = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e60';

    public function test_the_facts_of_a_subject_are_one_per_person_and_role(): void
    {
        $facts = [
            $this->fact(self::ANA, ActivityRole::Director),
            $this->fact(self::ANA, ActivityRole::Player),
            $this->fact(self::LUIS, ActivityRole::Player),
        ];

        $subject = new ActivitySubjectFactsDTO('42', $facts);

        $this->assertSame('42', $subject->subjectId);
        $this->assertSame($facts, $subject->facts);
    }

    public function test_a_subject_may_have_no_facts(): void
    {
        $this->assertSame([], (new ActivitySubjectFactsDTO('42', []))->facts);
    }

    public function test_the_same_person_twice_in_the_same_role_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ActivitySubjectFactsDTO('42', [
            $this->fact(self::ANA, ActivityRole::Player),
            $this->fact(strtoupper(self::ANA), ActivityRole::Player),
        ]);
    }

    public function test_facts_must_be_fact_dtos_in_a_list(): void
    {
        foreach ([['x' => $this->fact(self::ANA, ActivityRole::Player)], ['not a fact']] as $facts) {
            try {
                /** @phpstan-ignore argument.type (what a module may still pass) */
                new ActivitySubjectFactsDTO('42', $facts);
                $this->fail('Should be refused');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_subject_id_must_be_stable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ActivitySubjectFactsDTO('a b', []);
    }

    public function test_a_history_page_lists_each_subject_once_and_where_to_go_on(): void
    {
        $page = new ActivityHistoryPageDTO([
            new ActivitySubjectFactsDTO('1', [$this->fact(self::ANA, ActivityRole::Player)]),
            new ActivitySubjectFactsDTO('2', []),
        ], 'opaque-cursor');

        $this->assertSame(['1', '2'], array_map(static fn (ActivitySubjectFactsDTO $subject): string => $subject->subjectId, $page->subjects));
        $this->assertSame('opaque-cursor', $page->nextCursor);
        $this->assertFalse($page->isLast());
        $this->assertTrue((new ActivityHistoryPageDTO([], null))->isLast());
    }

    public function test_a_history_page_with_a_subject_twice_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ActivityHistoryPageDTO([new ActivitySubjectFactsDTO('1', []), new ActivitySubjectFactsDTO('1', [])], null);
    }

    public function test_a_history_page_with_an_empty_or_oversized_cursor_is_refused(): void
    {
        foreach (['', str_repeat('a', ActivityHistoryPageDTO::MAX_CURSOR_LENGTH + 1)] as $cursor) {
            try {
                new ActivityHistoryPageDTO([], $cursor);
                $this->fail('Should be refused');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_history_page_holds_subject_facts_only(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore argument.type (what a module may still pass) */
        new ActivityHistoryPageDTO(['1'], null);
    }

    public function test_a_described_subject_has_a_title_and_maybe_a_link(): void
    {
        $subject = new ActivitySubjectDTO('42', 'Mesa de Pathfinder', '/mesas/42');

        $this->assertSame('42', $subject->subjectId);
        $this->assertSame('Mesa de Pathfinder', $subject->title);
        $this->assertSame('/mesas/42', $subject->url);
        $this->assertNull((new ActivitySubjectDTO('42', 'Mesa'))->url);
    }

    public function test_a_described_subject_without_a_title_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ActivitySubjectDTO('42', ' ');
    }

    private function fact(string $userId, ActivityRole $role): ActivityFactDTO
    {
        return new ActivityFactDTO($userId, $role, new DateTimeImmutable('2026-11-20 18:00:00'));
    }
}
