<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class BlockBotsMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test route with the middleware
        Route::get('/test-bot-protection', function () {
            return response()->json(['message' => 'Success']);
        })->middleware('bot-protection');
    }

    public function test_full_http_request_with_blocked_bot_returns_403(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot', 'bingbot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.0; +https://openai.com/gptbot',
        ]);

        $response->assertStatus(403);
    }

    public function test_full_http_request_with_claudebot_returns_403(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot', 'bingbot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 ClaudeBot/1.0',
        ]);

        $response->assertStatus(403);
    }

    public function test_full_http_request_with_ccbot_returns_403(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot', 'bingbot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'CCBot/2.0 (https://commoncrawl.org/faq/)',
        ]);

        $response->assertStatus(403);
    }

    public function test_full_http_request_with_bytespider_returns_403(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['Bytespider']);
        Config::set('bot-protection.allowed_bots', []);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)',
        ]);

        $response->assertStatus(403);
    }

    public function test_full_http_request_with_perplexity_bot_returns_403(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['PerplexityBot']);
        Config::set('bot-protection.allowed_bots', []);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; PerplexityBot/1.0; +https://perplexity.ai/bot',
        ]);

        $response->assertStatus(403);
    }

    public function test_full_http_request_with_allowed_googlebot_returns_200(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot', 'bingbot', 'DuckDuckBot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_full_http_request_with_allowed_bingbot_returns_200(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot', 'bingbot', 'DuckDuckBot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_full_http_request_with_allowed_duckduckbot_returns_200(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot', 'bingbot', 'DuckDuckBot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'DuckDuckBot/1.0; (+http://duckduckgo.com/duckduckbot.html)',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_full_http_request_with_chrome_browser_returns_200(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot', 'bingbot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_full_http_request_with_firefox_browser_returns_200(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', []);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_full_http_request_with_safari_browser_returns_200(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', []);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_full_http_request_with_mobile_browser_returns_200(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot']);
        Config::set('bot-protection.allowed_bots', []);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_protection_can_be_disabled_globally(): void
    {
        Config::set('bot-protection.enabled', false);
        Config::set('bot-protection.blocked_bots', ['GPTBot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'GPTBot/1.0',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_empty_user_agent_is_allowed(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot']);

        $response = $this->get('/test-bot-protection');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_allowed_list_takes_precedence_in_full_request(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['Googlebot', 'GPTBot']);
        Config::set('bot-protection.allowed_bots', ['Googlebot']);

        $response = $this->get('/test-bot-protection', [
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Success']);
    }

    public function test_multiple_blocked_bots_in_sequence(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot', 'ClaudeBot', 'CCBot', 'Bytespider']);
        Config::set('bot-protection.allowed_bots', []);

        $blockedBots = [
            'GPTBot/1.0',
            'ClaudeBot/1.0',
            'CCBot/2.0',
            'Bytespider/1.0',
        ];

        foreach ($blockedBots as $bot) {
            $response = $this->get('/test-bot-protection', [
                'User-Agent' => $bot,
            ]);

            $response->assertStatus(403);
        }
    }

    public function test_post_request_with_blocked_bot_returns_403(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot']);
        Config::set('bot-protection.allowed_bots', []);

        Route::post('/test-bot-protection-post', function () {
            return response()->json(['message' => 'Created'], 201);
        })->middleware('bot-protection');

        $response = $this->post('/test-bot-protection-post', [], [
            'User-Agent' => 'GPTBot/1.0',
        ]);

        $response->assertStatus(403);
    }

    public function test_api_request_with_blocked_bot_returns_json_403(): void
    {
        Config::set('bot-protection.enabled', true);
        Config::set('bot-protection.blocked_bots', ['GPTBot']);
        Config::set('bot-protection.allowed_bots', []);

        $response = $this->getJson('/test-bot-protection', [
            'User-Agent' => 'GPTBot/1.0',
        ]);

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    }
}
