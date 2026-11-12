<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\ContentSecurityPolicySources;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentSecurityPolicySourcesTest extends TestCase
{
    private TestHandler $log;

    private ContentSecurityPolicySources $sources;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = new TestHandler;
        $this->sources = new ContentSecurityPolicySources(new Logger('test', [$this->log]));
    }

    public function test_nothing_is_allowed_until_a_module_asks(): void
    {
        $this->assertSame([], $this->sources->frameOrigins());
    }

    public function test_it_merges_every_module_origins_once_and_sorted(): void
    {
        $this->sources->allowFrames(fn (): array => ['https://player.example.com', 'https://embed.example.org']);
        $this->sources->allowFrames(fn (): array => ['https://embed.example.org', 'https://clips.example.net']);

        $this->assertSame(
            ['https://clips.example.net', 'https://embed.example.org', 'https://player.example.com'],
            $this->sources->frameOrigins(),
        );
    }

    public function test_it_reads_the_origins_each_time_so_settings_are_followed(): void
    {
        $enabled = ['https://player.example.com'];
        $this->sources->allowFrames(function () use (&$enabled): array {
            return $enabled;
        });

        $this->assertSame(['https://player.example.com'], $this->sources->frameOrigins());

        $enabled = [];

        $this->assertSame([], $this->sources->frameOrigins());
    }

    public function test_it_leaves_out_what_is_not_an_exact_https_origin(): void
    {
        $this->sources->allowFrames(fn (): array => [
            'https://player.example.com',
            '*',
            'https://*.example.com',
            "'self'",
            'data:',
            'http://plain.example.com',
            "https://a.example.com; script-src 'unsafe-inline'",
            42,
            null,
        ]);

        $this->assertSame(['https://player.example.com'], $this->sources->frameOrigins());
        $this->assertCount(8, $this->log->getRecords());
    }

    public function test_a_function_that_fails_adds_nothing_and_the_rest_stay(): void
    {
        $this->sources->allowFrames(function (): iterable {
            yield 'https://half.example.com';

            throw new RuntimeException('settings unreadable');
        });
        $this->sources->allowFrames(fn (): array => ['https://player.example.com']);

        $this->assertSame(['https://player.example.com'], $this->sources->frameOrigins());
        $this->assertTrue($this->log->hasWarningThatContains('none of them was added'));
        $this->assertCount(1, $this->log->getRecords());
    }
}
