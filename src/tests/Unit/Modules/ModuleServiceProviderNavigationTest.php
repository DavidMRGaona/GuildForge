<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Modules\ModuleServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Modules place their entries in the core's groups; the old contract to declare their own groups
 * is gone (core#429).
 */
final class ModuleServiceProviderNavigationTest extends TestCase
{
    public function test_modules_can_no_longer_declare_navigation_groups(): void
    {
        $this->assertFalse(method_exists(ModuleServiceProvider::class, 'registerNavigationGroups'));
    }
}
