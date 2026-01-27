<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Navigation\Persistence\Eloquent\Repositories;

use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Navigation\Persistence\Eloquent\Repositories\EloquentMenuItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentMenuItemRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentMenuItemRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentMenuItemRepository();
    }

    public function test_deactivate_by_module(): void
    {
        MenuItemModel::factory()->create([
            'module' => 'blog',
            'is_active' => true,
            'label' => 'Blog Item 1',
        ]);

        MenuItemModel::factory()->create([
            'module' => 'blog',
            'is_active' => true,
            'label' => 'Blog Item 2',
        ]);

        MenuItemModel::factory()->create([
            'module' => 'events',
            'is_active' => true,
            'label' => 'Events Item',
        ]);

        $this->repository->deactivateByModule('blog');

        $this->assertDatabaseHas('menu_items', [
            'label' => 'Blog Item 1',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'label' => 'Blog Item 2',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'label' => 'Events Item',
            'is_active' => true,
        ]);
    }

    public function test_activate_by_module(): void
    {
        MenuItemModel::factory()->create([
            'module' => 'blog',
            'is_active' => false,
            'label' => 'Blog Item 1',
        ]);

        MenuItemModel::factory()->create([
            'module' => 'blog',
            'is_active' => false,
            'label' => 'Blog Item 2',
        ]);

        MenuItemModel::factory()->create([
            'module' => 'events',
            'is_active' => false,
            'label' => 'Events Item',
        ]);

        $this->repository->activateByModule('blog');

        $this->assertDatabaseHas('menu_items', [
            'label' => 'Blog Item 1',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'label' => 'Blog Item 2',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'label' => 'Events Item',
            'is_active' => false,
        ]);
    }

    public function test_delete_by_module(): void
    {
        MenuItemModel::factory()->create([
            'module' => 'blog',
            'label' => 'Blog Item 1',
        ]);

        MenuItemModel::factory()->create([
            'module' => 'blog',
            'label' => 'Blog Item 2',
        ]);

        MenuItemModel::factory()->create([
            'module' => 'events',
            'label' => 'Events Item',
        ]);

        $this->repository->deleteByModule('blog');

        $this->assertDatabaseMissing('menu_items', [
            'label' => 'Blog Item 1',
        ]);

        $this->assertDatabaseMissing('menu_items', [
            'label' => 'Blog Item 2',
        ]);

        $this->assertDatabaseHas('menu_items', [
            'label' => 'Events Item',
        ]);

        $this->assertCount(1, MenuItemModel::all());
    }
}
