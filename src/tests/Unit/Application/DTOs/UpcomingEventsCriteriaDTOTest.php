<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\UpcomingEventsCriteriaDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpcomingEventsCriteriaDTOTest extends TestCase
{
    public function test_it_keeps_the_given_criteria(): void
    {
        $criteria = new UpcomingEventsCriteriaDTO(limit: 5, withinDays: 30, tagSlugs: ['rol', 'wargames']);

        $this->assertSame(5, $criteria->limit);
        $this->assertSame(30, $criteria->withinDays);
        $this->assertSame(['rol', 'wargames'], $criteria->tagSlugs);
    }

    public function test_it_has_no_tag_filter_by_default(): void
    {
        $criteria = new UpcomingEventsCriteriaDTO(limit: 5, withinDays: 30);

        $this->assertSame([], $criteria->tagSlugs);
    }

    public function test_it_drops_blank_and_repeated_tag_slugs(): void
    {
        $criteria = new UpcomingEventsCriteriaDTO(limit: 5, withinDays: 30, tagSlugs: ['rol', ' ', '', 'rol', 'wargames']);

        $this->assertSame(['rol', 'wargames'], $criteria->tagSlugs);
    }

    #[DataProvider('invalidCriteria')]
    public function test_it_rejects_invalid_criteria(int $limit, int $withinDays): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpcomingEventsCriteriaDTO(limit: $limit, withinDays: $withinDays);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidCriteria(): iterable
    {
        yield 'no events' => [0, 30];
        yield 'negative limit' => [-1, 30];
        yield 'no days' => [5, 0];
        yield 'negative days' => [5, -3];
    }
}
