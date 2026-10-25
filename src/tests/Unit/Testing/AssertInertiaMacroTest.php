<?php

declare(strict_types=1);

namespace Tests\Unit\Testing;

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

final class AssertInertiaMacroTest extends TestCase
{
    public function test_it_accepts_a_page_without_history_flags(): void
    {
        $response = $this->responseWithPage([
            'component' => 'Home',
            'props' => ['title' => 'Inicio'],
            'url' => '/',
            'version' => 'abc',
        ]);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home', false)
            ->where('title', 'Inicio'));
    }

    public function test_it_still_rejects_a_page_without_a_component(): void
    {
        $response = $this->responseWithPage(['props' => [], 'url' => '/', 'version' => 'abc']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Not a valid Inertia response.');

        $response->assertInertia();
    }

    /**
     * @param  array<string, mixed>  $page
     * @return TestResponse<Response>
     */
    private function responseWithPage(array $page): TestResponse
    {
        $base = new Response('');
        $base->original = view('app', ['page' => $page]);

        return TestResponse::fromBaseResponse($base);
    }
}
