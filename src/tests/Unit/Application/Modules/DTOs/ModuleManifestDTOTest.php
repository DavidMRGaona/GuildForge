<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Modules\DTOs;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ModuleManifestDTOTest extends TestCase
{
    public function test_it_creates_module_manifest_dto_from_constructor_with_all_properties(): void
    {
        $dto = new ModuleManifestDTO(
            name: 'forum',
            version: '1.0.0',
            namespace: 'Modules\\Forum',
            provider: 'Modules\\Forum\\ForumServiceProvider',
            description: 'Forum module for discussions',
            author: 'Runesword Team',
            requires: ['php' => '^8.2', 'laravel' => '^11.0'],
            dependencies: ['core-auth', 'notifications'],
        );

        $this->assertEquals('forum', $dto->name);
        $this->assertEquals('1.0.0', $dto->version);
        $this->assertEquals('Modules\\Forum', $dto->namespace);
        $this->assertEquals('Modules\\Forum\\ForumServiceProvider', $dto->provider);
        $this->assertEquals('Forum module for discussions', $dto->description);
        $this->assertEquals('Runesword Team', $dto->author);
        $this->assertEquals(['php' => '^8.2', 'laravel' => '^11.0'], $dto->requires);
        $this->assertEquals(['core-auth', 'notifications'], $dto->dependencies);
    }

    public function test_it_creates_from_array_via_from_array_static_method(): void
    {
        $data = [
            'name' => 'game-library',
            'version' => '2.1.5',
            'namespace' => 'Modules\\GameLibrary',
            'provider' => 'Modules\\GameLibrary\\GameLibraryServiceProvider',
            'description' => 'Manage game collection',
            'author' => 'Community Contributors',
            'requires' => [
                'php' => '^8.2',
                'ext-json' => '*',
            ],
            'dependencies' => ['core-database'],
        ];

        $dto = ModuleManifestDTO::fromArray($data);

        $this->assertEquals('game-library', $dto->name);
        $this->assertEquals('2.1.5', $dto->version);
        $this->assertEquals('Modules\\GameLibrary', $dto->namespace);
        $this->assertEquals('Modules\\GameLibrary\\GameLibraryServiceProvider', $dto->provider);
        $this->assertEquals('Manage game collection', $dto->description);
        $this->assertEquals('Community Contributors', $dto->author);
        $this->assertEquals(['php' => '^8.2', 'ext-json' => '*'], $dto->requires);
        $this->assertEquals(['core-database'], $dto->dependencies);
    }

    public function test_it_throws_exception_for_missing_required_name_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: name');

        ModuleManifestDTO::fromArray([
            'version' => '1.0.0',
            'namespace' => 'Modules\\Test',
            'provider' => 'Modules\\Test\\TestServiceProvider',
        ]);
    }

    public function test_it_throws_exception_for_missing_required_version_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: version');

        ModuleManifestDTO::fromArray([
            'name' => 'test-module',
            'namespace' => 'Modules\\Test',
            'provider' => 'Modules\\Test\\TestServiceProvider',
        ]);
    }

    public function test_it_throws_exception_for_missing_required_namespace_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: namespace');

        ModuleManifestDTO::fromArray([
            'name' => 'test-module',
            'version' => '1.0.0',
            'provider' => 'Modules\\Test\\TestServiceProvider',
        ]);
    }

    public function test_it_throws_exception_for_missing_required_provider_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: provider');

        ModuleManifestDTO::fromArray([
            'name' => 'test-module',
            'version' => '1.0.0',
            'namespace' => 'Modules\\Test',
        ]);
    }

    public function test_it_handles_optional_fields_with_null_values(): void
    {
        $dto = new ModuleManifestDTO(
            name: 'minimal-module',
            version: '1.0.0',
            namespace: 'Modules\\Minimal',
            provider: 'Modules\\Minimal\\MinimalServiceProvider',
            description: null,
            author: null,
            requires: [],
            dependencies: [],
        );

        $this->assertNull($dto->description);
        $this->assertNull($dto->author);
        $this->assertEquals([], $dto->requires);
        $this->assertEquals([], $dto->dependencies);
    }

    public function test_it_handles_optional_fields_when_missing_from_array(): void
    {
        $data = [
            'name' => 'simple-module',
            'version' => '0.1.0',
            'namespace' => 'Modules\\Simple',
            'provider' => 'Modules\\Simple\\SimpleServiceProvider',
        ];

        $dto = ModuleManifestDTO::fromArray($data);

        $this->assertNull($dto->description);
        $this->assertNull($dto->author);
        $this->assertEquals([], $dto->requires);
        $this->assertEquals([], $dto->dependencies);
    }

    public function test_to_array_returns_correct_array_representation(): void
    {
        $dto = new ModuleManifestDTO(
            name: 'shop',
            version: '3.2.1',
            namespace: 'Modules\\Shop',
            provider: 'Modules\\Shop\\ShopServiceProvider',
            description: 'E-commerce shop module',
            author: 'Shop Team',
            requires: ['php' => '^8.2', 'laravel' => '^11.0'],
            dependencies: ['payment-gateway', 'inventory'],
        );

        $array = $dto->toArray();

        $expected = [
            'name' => 'shop',
            'version' => '3.2.1',
            'namespace' => 'Modules\\Shop',
            'provider' => 'Modules\\Shop\\ShopServiceProvider',
            'description' => 'E-commerce shop module',
            'author' => 'Shop Team',
            'requires' => ['php' => '^8.2', 'laravel' => '^11.0'],
            'dependencies' => ['payment-gateway', 'inventory'],
        ];

        $this->assertEquals($expected, $array);
    }

    public function test_to_array_omits_null_optional_fields(): void
    {
        $dto = new ModuleManifestDTO(
            name: 'basic',
            version: '1.0.0',
            namespace: 'Modules\\Basic',
            provider: 'Modules\\Basic\\BasicServiceProvider',
            description: null,
            author: null,
            requires: [],
            dependencies: [],
        );

        $array = $dto->toArray();

        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('author', $array);
        $this->assertArrayHasKey('requires', $array);
        $this->assertArrayHasKey('dependencies', $array);
    }
}
