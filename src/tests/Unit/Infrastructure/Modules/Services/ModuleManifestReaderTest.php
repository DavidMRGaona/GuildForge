<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Infrastructure\Modules\Services\ModuleManifestReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class ModuleManifestReaderTest extends TestCase
{
    public function test_a_malformed_list_requirement_is_reported_as_ignored_entries_not_as_incompatible(): void
    {
        $logger = new class extends AbstractLogger
        {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
        $path = tempnam(sys_get_temp_dir(), 'gf-manifest-');
        file_put_contents($path, json_encode([
            'name' => 'demo', 'version' => '1.0.0', 'namespace' => 'Modules\\Demo', 'provider' => 'DemoServiceProvider',
            'requires' => ['core' => '^3.0', 'extensions' => 'intl'],
        ], JSON_THROW_ON_ERROR));

        try {
            $manifest = (new ModuleManifestReader($logger))->read((string) $path);
        } finally {
            unlink((string) $path);
        }

        $this->assertSame([], $manifest->requires['extensions'] ?? null);
        $this->assertCount(1, $logger->messages);
        $this->assertStringContainsString('requires.extensions', $logger->messages[0]);
        $this->assertStringNotContainsString('incompatible', $logger->messages[0]);
    }
}
