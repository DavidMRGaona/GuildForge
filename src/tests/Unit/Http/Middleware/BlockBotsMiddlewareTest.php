<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\BlockBotsMiddleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class BlockBotsMiddlewareTest extends TestCase
{
    private BlockBotsMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new BlockBotsMiddleware();
    }

    public function test_it_passes_request_when_protection_is_disabled(): void
    {
        Config::set('bot-protection.enabled', false);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'GPTBot/1.0');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    public function test_it_passes_request_for_empty_user_agent(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot']);

        $request = Request::create('/test', 'GET');
        // No User-Agent header set

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_allows_googlebot(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_allows_bingbot(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot']);
        Config::set('bot-protection.allowed_bots', ['bingbot']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_allows_duckduckbot(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot']);
        Config::set('bot-protection.allowed_bots', ['DuckDuckBot']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'DuckDuckBot/1.0; (+http://duckduckgo.com/duckduckbot.html)');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_blocks_gptbot(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.0; +https://openai.com/gptbot');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_claudebot(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['ClaudeBot']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 ClaudeBot/1.0');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_ccbot(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['CCBot']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'CCBot/2.0 (https://commoncrawl.org/faq/)');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_bytespider(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['Bytespider']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_anthropic_ai(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['anthropic-ai']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'anthropic-ai');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_cohere_ai(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['cohere-ai']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'cohere-ai');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_omgili(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['Omgili']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Omgili/1.0)');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_facebook_external_hit(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['facebookexternalhit']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_perplexity_bot(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['PerplexityBot']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; PerplexityBot/1.0; +https://perplexity.ai/bot');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_blocks_img2dataset(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['img2dataset']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'img2dataset');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_allowed_list_takes_precedence_over_blocked_list(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'Googlebot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_allows_normal_browser_user_agent(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_allows_firefox_user_agent(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_allows_safari_user_agent(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_logs_blocked_bot_when_logging_is_enabled(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot']);
        Config::set('bot-protection.allowed_bots', []);
        Config::set('bot-protection.log_blocks', true);

        Log::shouldReceive('info')
            ->once()
            ->with('Blocked bot access', [
                'user_agent' => 'GPTBot/1.0',
                'ip' => '127.0.0.1',
                'url' => 'http://localhost/test',
            ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'GPTBot/1.0');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_does_not_log_blocked_bot_when_logging_is_disabled(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot']);
        Config::set('bot-protection.allowed_bots', []);
        Config::set('bot-protection.log_blocks', false);

        Log::shouldReceive('info')->never();

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'GPTBot/1.0');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_does_not_log_when_bot_is_allowed(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', []);
        Config::set('bot-protection.allowed_bots', ['Googlebot']);
        Config::set('bot-protection.log_blocks', true);

        Log::shouldReceive('info')->never();

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Googlebot/2.1');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_bot_detection_is_case_insensitive(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 gptbot/1.0');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_returns_json_response_for_json_requests(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot']);
        Config::set('bot-protection.allowed_bots', []);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'GPTBot/1.0');
        $request->headers->set('Accept', 'application/json');

        $next = fn (Request $req) => response('OK', 200);

        $response = $this->middleware->handle($request, $next);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }
}
