<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Authorization\Services;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\Exceptions\InvalidPermissionKeyException;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Infrastructure\Authorization\Services\PermissionRegistry;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A key outside the "resource.action" form used to be accepted and only broke the database sync,
 * which stopped at the first bad key and saved no permission at all (core#402).
 */
final class PermissionRegistryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function validKeys(): iterable
    {
        yield 'core' => ['menu_items.view_any', null];
        yield 'module' => ['example-module:settings.manage', 'example-module'];
    }

    #[DataProvider('validKeys')]
    public function test_registers_a_key_in_the_resource_action_form(string $key, ?string $module): void
    {
        foreach ([true, false] as $rejectsInvalidKeys) {
            $registry = new PermissionRegistry(rejectsInvalidKeys: $rejectsInvalidKeys);

            $registry->register($this->definition($key, $module));

            $this->assertTrue($registry->has($key));
        }
    }

    public function test_rejecting_registry_throws_naming_the_module_and_the_key(): void
    {
        $registry = new PermissionRegistry(rejectsInvalidKeys: true);

        try {
            $registry->register($this->definition('example-module:manage', 'example-module'));
            $this->fail('An invalid key must be rejected.');
        } catch (InvalidPermissionKeyException $e) {
            $this->assertStringContainsString('"example-module"', $e->getMessage());
            $this->assertStringContainsString('"example-module:manage"', $e->getMessage());
            $this->assertStringContainsString('"resource.action"', $e->getMessage());
        }

        $this->assertSame([], $registry->all());
    }

    public function test_rejecting_registry_throws_for_an_invalid_core_key(): void
    {
        $registry = new PermissionRegistry(rejectsInvalidKeys: true);

        $this->expectException(InvalidPermissionKeyException::class);
        $this->expectExceptionMessage('"manage"');

        $registry->register($this->definition('manage', null));
    }

    public function test_lenient_registry_skips_an_invalid_key_with_a_warning_and_keeps_the_rest(): void
    {
        $log = Log::spy();
        $registry = new PermissionRegistry(rejectsInvalidKeys: false);

        $registry->registerMany([
            $this->definition('example-module:manage', 'example-module'),
            $this->definition('example-module:settings.manage', 'example-module'),
            $this->definition('menu_items.view_any', null),
        ]);

        $this->assertFalse($registry->has('example-module:manage'));
        $this->assertTrue($registry->has('example-module:settings.manage'));
        $this->assertTrue($registry->has('menu_items.view_any'));

        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => str_contains($message, '"example-module"')
                && $context === ['module' => 'example-module', 'key' => 'example-module:manage'],
        );
    }

    public function test_sync_saves_every_valid_permission_when_one_was_invalid(): void
    {
        Log::spy();
        $saved = [];
        $repository = Mockery::mock(PermissionRepositoryInterface::class);
        $repository->shouldReceive('saveMany')->once()->andReturnUsing(function (array $permissions) use (&$saved): void {
            $saved = $permissions;
        });
        $registry = new PermissionRegistry($repository, rejectsInvalidKeys: false);

        $registry->registerMany([
            $this->definition('example-module:manage', 'example-module'),
            $this->definition('example-module:settings.manage', 'example-module'),
            $this->definition('menu_items.view_any', null),
        ]);
        $registry->syncToDatabase();

        $this->assertSame(
            ['example-module:settings.manage', 'menu_items.view_any'],
            array_map(fn (Permission $permission): string => $permission->key()->value, $saved),
        );
    }

    private function definition(string $key, ?string $module): PermissionDefinitionDTO
    {
        $name = $module !== null ? substr($key, strlen($module) + 1) : $key;
        [$resource, $action] = array_pad(explode('.', $name, 2), 2, $name);

        return new PermissionDefinitionDTO(
            key: $key,
            label: 'Permiso de ejemplo',
            resource: $resource,
            action: $action,
            module: $module,
        );
    }
}
