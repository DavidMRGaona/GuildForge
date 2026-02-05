<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Concerns;

use App\Http\Concerns\BuildsPaginatedResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

final class BuildsPaginatedResponseTest extends TestCase
{
    private object $traitUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an anonymous class that uses the trait
        $this->traitUser = new class () {
            use BuildsPaginatedResponse;

            public function callGetCurrentPage(): int
            {
                return $this->getCurrentPage();
            }
        };
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
}
