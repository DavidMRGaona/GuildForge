<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Concerns;

use App\Http\Concerns\BuildsPaginatedResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Tests\TestCase;

final class BuildsPaginatedResponseTest extends TestCase
{
    private PaginatedResponseBuilder $traitUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->traitUser = new PaginatedResponseBuilder;
    }

    public function test_get_current_page_returns_one_when_no_page_parameter(): void
    {
        $request = Request::create('/test');
        $this->app->instance('request', $request);

        $result = $this->traitUser->callGetCurrentPage();

        $this->assertEquals(1, $result);
    }

    public function test_get_current_page_returns_page_number_from_query(): void
    {
        $request = Request::create('/test?page=5');
        $this->app->instance('request', $request);

        $result = $this->traitUser->callGetCurrentPage();

        $this->assertEquals(5, $result);
    }

    public function test_get_current_page_returns_one_when_page_is_zero(): void
    {
        $request = Request::create('/test?page=0');
        $this->app->instance('request', $request);

        $result = $this->traitUser->callGetCurrentPage();

        $this->assertEquals(1, $result);
    }

    public function test_get_current_page_returns_one_when_page_is_negative(): void
    {
        $request = Request::create('/test?page=-3');
        $this->app->instance('request', $request);

        $result = $this->traitUser->callGetCurrentPage();

        $this->assertEquals(1, $result);
    }

    public function test_get_current_page_returns_one_when_page_is_not_numeric(): void
    {
        $request = Request::create('/test?page=abc');
        $this->app->instance('request', $request);

        $result = $this->traitUser->callGetCurrentPage();

        $this->assertEquals(1, $result);
    }

    public function test_get_current_page_handles_large_page_numbers(): void
    {
        $request = Request::create('/test?page=9999');
        $this->app->instance('request', $request);

        $result = $this->traitUser->callGetCurrentPage();

        $this->assertEquals(9999, $result);
    }

    public function test_get_current_page_handles_decimal_page_numbers(): void
    {
        $request = Request::create('/test?page=3.7');
        $this->app->instance('request', $request);

        $result = $this->traitUser->callGetCurrentPage();

        // Cast to int should convert 3.7 to 3
        $this->assertEquals(3, $result);
    }

    public function test_the_links_keep_the_filters_of_the_list(): void
    {
        $this->app->instance('request', Request::create('/mesas?format=in_person&status=scheduled&page=2'));

        $links = $this->traitUser->callBuildPaginatedResponse(page: 2, total: 30, perPage: 10)['links'];

        $this->assertSame('http://localhost/mesas?format=in_person&status=scheduled&page=1', $links['first']);
        $this->assertSame('http://localhost/mesas?format=in_person&status=scheduled&page=1', $links['prev']);
        $this->assertSame('http://localhost/mesas?format=in_person&status=scheduled&page=3', $links['next']);
        $this->assertSame('http://localhost/mesas?format=in_person&status=scheduled&page=3', $links['last']);
    }

    // Filters with several values, as the tags of events and articles
    public function test_the_links_keep_filters_with_several_values(): void
    {
        $this->app->instance('request', Request::create('/eventos?tags[]=rol&tags[]=wargames'));

        $next = $this->traitUser->callBuildPaginatedResponse(page: 1, total: 30, perPage: 10)['links']['next'];

        $this->assertIsString($next);
        parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame(['tags' => ['rol', 'wargames'], 'page' => '2'], $query);
    }

    public function test_the_links_without_filters_only_carry_the_page(): void
    {
        $this->app->instance('request', Request::create('/articulos'));

        $links = $this->traitUser->callBuildPaginatedResponse(page: 1, total: 30, perPage: 10)['links'];

        $this->assertSame('http://localhost/articulos?page=2', $links['next']);
        $this->assertNull($links['prev']);
    }
}

/**
 * Uses the trait so the tests can call its protected methods.
 */
final class PaginatedResponseBuilder
{
    use BuildsPaginatedResponse;

    public function callGetCurrentPage(): int
    {
        return $this->getCurrentPage();
    }

    /**
     * @return array{data: array<int, mixed>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}, links: array{first: string, last: string, prev: string|null, next: string|null}}
     */
    public function callBuildPaginatedResponse(int $page, int $total, int $perPage): array
    {
        return $this->buildPaginatedResponse(
            items: [],
            total: $total,
            page: $page,
            perPage: $perPage,
            resourceClass: JsonResource::class,
        );
    }
}
