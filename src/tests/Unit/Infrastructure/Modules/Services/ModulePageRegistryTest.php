<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Infrastructure\Modules\Services\ModulePageRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Module pages that use the core layout leave it to the app: the core renders its one
 * DefaultLayout around them, kept across visits. Pages of other prefixes wrap their own.
 */
final class ModulePageRegistryTest extends TestCase
{
    public function test_a_prefix_wraps_its_own_layout_unless_it_says_otherwise(): void
    {
        $this->assertFalse((new PagePrefixDTO(prefix: 'Legacy', module: 'legacy-module'))->usesCoreLayout);
    }

    public function test_it_lists_the_prefixes_that_use_the_core_layout(): void
    {
        $registry = new ModulePageRegistry;
        $registry->registerMany([
            new PagePrefixDTO(prefix: 'GameTables', module: 'game-tables', usesCoreLayout: true),
            new PagePrefixDTO(prefix: 'Campaigns', module: 'game-tables', usesCoreLayout: true),
            new PagePrefixDTO(prefix: 'Legacy', module: 'legacy-module'),
        ]);

        $this->assertSame(['GameTables', 'Campaigns'], $registry->coreLayoutPrefixes());
        $this->assertSame(
            ['GameTables' => 'game-tables', 'Campaigns' => 'game-tables', 'Legacy' => 'legacy-module'],
            $registry->toInertiaPayload(),
        );
    }

    public function test_unregistering_a_module_drops_its_core_layout_prefixes(): void
    {
        $registry = new ModulePageRegistry;
        $registry->register(new PagePrefixDTO(prefix: 'GameTables', module: 'game-tables', usesCoreLayout: true));

        $registry->unregisterModule('game-tables');

        $this->assertSame([], $registry->coreLayoutPrefixes());
    }

    public function test_the_layout_choice_survives_the_array_round_trip(): void
    {
        $dto = new PagePrefixDTO(prefix: 'GameTables', module: 'game-tables', usesCoreLayout: true);

        $this->assertSame(['prefix' => 'GameTables', 'module' => 'game-tables', 'usesCoreLayout' => true], $dto->toArray());
        $this->assertTrue(PagePrefixDTO::fromArray($dto->toArray())->usesCoreLayout);
        $this->assertFalse(PagePrefixDTO::fromArray(['prefix' => 'Legacy', 'module' => 'legacy-module'])->usesCoreLayout);
    }
}
