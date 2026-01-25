<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\SearchRequest;
use Tests\TestCase;

final class SearchRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new SearchRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_rules_validates_q_parameter(): void
    {
        $request = new SearchRequest();

        $rules = $request->rules();

        $this->assertArrayHasKey('q', $rules);
        $this->assertContains('nullable', $rules['q']);
        $this->assertContains('string', $rules['q']);
        $this->assertContains('max:100', $rules['q']);
    }

    public function test_search_query_returns_trimmed_query(): void
    {
        $request = SearchRequest::create('/search', 'GET', ['q' => '  test query  ']);

        $this->assertSame('test query', $request->searchQuery());
    }

    public function test_search_query_returns_empty_string_when_missing(): void
    {
        $request = SearchRequest::create('/search', 'GET');

        $this->assertSame('', $request->searchQuery());
    }

    public function test_has_valid_search_query_returns_true_for_valid_query(): void
    {
        $request = SearchRequest::create('/search', 'GET', ['q' => 'test']);

        $this->assertTrue($request->hasValidSearchQuery());
    }

    public function test_has_valid_search_query_returns_false_for_short_query(): void
    {
        $request = SearchRequest::create('/search', 'GET', ['q' => 'a']);

        $this->assertFalse($request->hasValidSearchQuery());
    }

    public function test_has_valid_search_query_returns_false_for_empty_query(): void
    {
        $request = SearchRequest::create('/search', 'GET', ['q' => '']);

        $this->assertFalse($request->hasValidSearchQuery());
    }

    public function test_is_query_too_short_returns_true_for_single_character(): void
    {
        $request = SearchRequest::create('/search', 'GET', ['q' => 'a']);

        $this->assertTrue($request->isQueryTooShort());
    }

    public function test_is_query_too_short_returns_false_for_empty_query(): void
    {
        $request = SearchRequest::create('/search', 'GET', ['q' => '']);

        $this->assertFalse($request->isQueryTooShort());
    }

    public function test_is_query_too_short_returns_false_for_valid_query(): void
    {
        $request = SearchRequest::create('/search', 'GET', ['q' => 'test']);

        $this->assertFalse($request->isQueryTooShort());
    }

    public function test_minimum_query_length_is_two_characters(): void
    {
        $oneChar = SearchRequest::create('/search', 'GET', ['q' => 'a']);
        $twoChars = SearchRequest::create('/search', 'GET', ['q' => 'ab']);

        $this->assertFalse($oneChar->hasValidSearchQuery());
        $this->assertTrue($twoChars->hasValidSearchQuery());
    }
}
