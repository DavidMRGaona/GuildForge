<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use App\Infrastructure\Support\QueryHelpers;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

final class QueryHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_apply_tag_filter_adds_where_has_when_tags_provided(): void
    {
        /** @var Builder<\Illuminate\Database\Eloquent\Model>&MockInterface $builder */
        $builder = Mockery::mock(Builder::class);

        $tagSlugs = ['warhammer', 'dnd'];

        $builder->shouldReceive('whereHas')
            ->once()
            ->with('tags', Mockery::type('Closure'))
            ->andReturnSelf();

        $result = QueryHelpers::applyTagFilter($builder, $tagSlugs);

        $this->assertSame($builder, $result);
    }

    public function test_apply_tag_filter_returns_unchanged_builder_when_null(): void
    {
        /** @var Builder<\Illuminate\Database\Eloquent\Model>&MockInterface $builder */
        $builder = Mockery::mock(Builder::class);

        $builder->shouldNotReceive('whereHas');

        $result = QueryHelpers::applyTagFilter($builder, null);

        $this->assertSame($builder, $result);
    }

    public function test_apply_tag_filter_returns_unchanged_builder_when_empty_array(): void
    {
        /** @var Builder<\Illuminate\Database\Eloquent\Model>&MockInterface $builder */
        $builder = Mockery::mock(Builder::class);

        $builder->shouldNotReceive('whereHas');

        $result = QueryHelpers::applyTagFilter($builder, []);

        $this->assertSame($builder, $result);
    }

    public function test_apply_pagination_calculates_offset_correctly(): void
    {
        /** @var Builder<\Illuminate\Database\Eloquent\Model>&MockInterface $builder */
        $builder = Mockery::mock(Builder::class);

        // Page 1, 10 per page -> offset 0
        $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
        $builder->shouldReceive('limit')->once()->with(10)->andReturnSelf();

        $result = QueryHelpers::applyPagination($builder, page: 1, perPage: 10);

        $this->assertSame($builder, $result);
    }

    public function test_apply_pagination_calculates_offset_for_page_3(): void
    {
        /** @var Builder<\Illuminate\Database\Eloquent\Model>&MockInterface $builder */
        $builder = Mockery::mock(Builder::class);

        // Page 3, 12 per page -> offset 24
        $builder->shouldReceive('offset')->once()->with(24)->andReturnSelf();
        $builder->shouldReceive('limit')->once()->with(12)->andReturnSelf();

        $result = QueryHelpers::applyPagination($builder, page: 3, perPage: 12);

        $this->assertSame($builder, $result);
    }

    public function test_calculate_offset_returns_correct_value(): void
    {
        $this->assertEquals(0, QueryHelpers::calculateOffset(page: 1, perPage: 10));
        $this->assertEquals(10, QueryHelpers::calculateOffset(page: 2, perPage: 10));
        $this->assertEquals(24, QueryHelpers::calculateOffset(page: 3, perPage: 12));
        $this->assertEquals(100, QueryHelpers::calculateOffset(page: 11, perPage: 10));
    }
}
