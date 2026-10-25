<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\View\Components\ModuleImportMap;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

final class ModuleImportMapTest extends TestCase
{
    public function test_maps_every_shared_package_and_public_entry_of_the_list(): void
    {
        Vite::shouldReceive('asset')->andReturnUsing(
            fn (string $entry): string => '/build/assets/'.pathinfo($entry, PATHINFO_FILENAME).'-ABC123.js'
        );

        $html = (new ModuleImportMap)->render()->render();

        $this->assertStringContainsString('<script type="importmap">', $html);
        foreach ($this->publicApi() as $specifier => $entry) {
            $asset = '/build/assets/'.pathinfo($entry, PATHINFO_FILENAME).'-ABC123.js';
            $this->assertStringContainsString("\"{$specifier}\": \"{$asset}\"", $html, $specifier);
        }
    }

    public function test_leaves_out_an_entry_the_manifest_lacks(): void
    {
        Vite::shouldReceive('asset')->andReturnUsing(function (string $entry): string {
            if ($entry === 'resources/js/vendor-exports/pinia.ts') {
                throw new \RuntimeException('Manifest error');
            }

            return '/build/assets/'.pathinfo($entry, PATHINFO_FILENAME).'.js';
        });

        $html = (new ModuleImportMap)->render()->render();

        $this->assertStringNotContainsString('"pinia"', $html);
        $this->assertStringContainsString('"vue": "/build/assets/vue.js"', $html);
        $this->assertStringContainsString('"@/utils/datetime": "/build/assets/datetime.js"', $html);
    }

    public function test_renders_nothing_without_a_manifest(): void
    {
        Vite::shouldReceive('asset')->andThrow(new \Exception('Manifest not found'));

        $this->assertEmpty((new ModuleImportMap)->render()->render());
    }

    /**
     * The shared packages and the public entries, from the core's list.
     *
     * @return array<string, string>
     */
    private function publicApi(): array
    {
        /** @var array{packages: array<string, string>, entries: array<string, string>} $list */
        $list = json_decode((string) file_get_contents(resource_path('js/public-api.json')), true, flags: JSON_THROW_ON_ERROR);

        return [...$list['packages'], ...$list['entries']];
    }
}
