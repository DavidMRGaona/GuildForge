<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bot protection enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable bot protection globally. When disabled, all requests
    | are allowed through regardless of user agent.
    |
    */
    'enabled' => env('BOT_PROTECTION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Blocked bots
    |--------------------------------------------------------------------------
    |
    | User agent patterns that will be blocked. These are AI scrapers and
    | crawlers that consume resources without providing meaningful traffic.
    | Detection is case-insensitive.
    |
    */
    'blocked_bots' => [
        // OpenAI
        'GPTBot',
        'ChatGPT-User',
        'OAI-SearchBot',

        // Anthropic
        'ClaudeBot',
        'Claude-Web',
        'anthropic-ai',

        // Common Crawl
        'CCBot',

        // Bytedance
        'Bytespider',

        // Google AI (not search)
        'Google-Extended',

        // Apple AI
        'Applebot-Extended',

        // Cohere
        'cohere-ai',

        // Meta AI
        'Meta-ExternalAgent',
        'Meta-ExternalFetcher',

        // Perplexity
        'PerplexityBot',

        // Others
        'Amazonbot',
        'PetalBot',
        'YouBot',
        'Diffbot',
        'Omgilibot',
        'Omgili',
        'img2dataset',
        'Scrapy',
        'facebookexternalhit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed bots
    |--------------------------------------------------------------------------
    |
    | User agent patterns that are explicitly allowed. These take precedence
    | over the blocked list. Includes SEO crawlers and social preview bots.
    | Detection is case-insensitive.
    |
    */
    'allowed_bots' => [
        // Google Search
        'Googlebot',
        'Googlebot-Image',
        'Googlebot-News',

        // Bing
        'bingbot',
        'msnbot',
        'BingPreview',

        // Other SEO crawlers
        'Slurp',
        'DuckDuckBot',
        'YandexBot',
        'Baiduspider',
        'Applebot',

        // Social preview bots
        'Twitterbot',
        'LinkedInBot',
        'Slackbot',
        'Discordbot',
        'WhatsApp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log blocked requests
    |--------------------------------------------------------------------------
    |
    | When enabled, blocked bot requests will be logged with user agent, IP,
    | and URL for monitoring purposes.
    |
    */
    'log_blocks' => env('BOT_LOG_BLOCKED', false),
];
