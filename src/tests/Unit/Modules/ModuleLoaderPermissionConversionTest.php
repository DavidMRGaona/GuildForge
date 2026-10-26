<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Modules\ModuleLoader;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

final class ModuleLoaderPermissionConversionTest extends TestCase
{
    public function test_module_permission_keeps_description_and_group(): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        $dto = new PermissionDTO(
            name: 'settings.manage',
            label: 'L',
            group: 'Socios',
            description: 'D',
            module: 'memberships',
        );

        $converted = (new ReflectionMethod($loader, 'convertPermissionDTO'))->invoke($loader, $dto);

        $this->assertSame('memberships:settings.manage', $converted->key);
        $this->assertSame('D', $converted->description);
        $this->assertSame('Socios', $converted->group);
    }
}
