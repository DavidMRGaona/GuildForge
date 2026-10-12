<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Infrastructure\Modules\Services\ModuleNavigationRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleNavigationRegistryTest extends TestCase
{
    private ModuleNavigationRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ModuleNavigationRegistry;
    }

    public function test_it_registers_single_navigation_item(): void
    {
        $item = new NavigationItemDTO(
            label: 'Dashboard',
            route: 'admin.dashboard',
            module: 'admin',
        );

        $this->registry->register($item);

        $all = $this->registry->all();
        $this->assertCount(1, $all);
        $this->assertEquals('Dashboard', $all[0]->label);
    }

    public function test_it_registers_multiple_navigation_items(): void
    {
        $items = [
            new NavigationItemDTO(label: 'Home', route: 'home', module: 'core'),
            new NavigationItemDTO(label: 'About', route: 'about', module: 'core'),
        ];

        $this->registry->registerMany($items);

        $all = $this->registry->all();
        $this->assertCount(2, $all);
    }

    public function test_all_returns_all_registered_items(): void
    {
        $item1 = new NavigationItemDTO(label: 'Events', module: 'events');
        $item2 = new NavigationItemDTO(label: 'Articles', module: 'articles');
        $item3 = new NavigationItemDTO(label: 'Gallery', module: 'gallery');

        $this->registry->register($item1);
        $this->registry->register($item2);
        $this->registry->register($item3);

        $all = $this->registry->all();

        $this->assertCount(3, $all);
    }

    public function test_for_module_returns_items_for_specific_module(): void
    {
        $blogItem = new NavigationItemDTO(label: 'Posts', module: 'blog');
        $forumItem = new NavigationItemDTO(label: 'Threads', module: 'forum');
        $blogItem2 = new NavigationItemDTO(label: 'Categories', module: 'blog');

        $this->registry->register($blogItem);
        $this->registry->register($forumItem);
        $this->registry->register($blogItem2);

        $blogItems = $this->registry->forModule('blog');

        $this->assertCount(2, $blogItems);
        $this->assertEquals('Posts', $blogItems[0]->label);
        $this->assertEquals('Categories', $blogItems[1]->label);
    }

    public function test_for_module_returns_empty_array_when_no_items_for_module(): void
    {
        $item = new NavigationItemDTO(label: 'Test', module: 'test');
        $this->registry->register($item);

        $result = $this->registry->forModule('nonexistent');

        $this->assertEmpty($result);
    }

    public function test_grouped_returns_items_grouped_by_group(): void
    {
        $item1 = new NavigationItemDTO(label: 'Dashboard', group: 'main', module: 'admin');
        $item2 = new NavigationItemDTO(label: 'Settings', group: 'main', module: 'admin');
        $item3 = new NavigationItemDTO(label: 'Posts', group: 'content', module: 'blog');

        $this->registry->register($item1);
        $this->registry->register($item2);
        $this->registry->register($item3);

        $grouped = $this->registry->grouped();

        $this->assertArrayHasKey('main', $grouped);
        $this->assertArrayHasKey('content', $grouped);
        $this->assertCount(2, $grouped['main']);
        $this->assertCount(1, $grouped['content']);
    }

    public function test_grouped_sorts_items_within_groups_by_sort_order(): void
    {
        $item1 = new NavigationItemDTO(label: 'Third', group: 'main', sort: 30, module: 'test');
        $item2 = new NavigationItemDTO(label: 'First', group: 'main', sort: 10, module: 'test');
        $item3 = new NavigationItemDTO(label: 'Second', group: 'main', sort: 20, module: 'test');

        $this->registry->register($item1);
        $this->registry->register($item2);
        $this->registry->register($item3);

        $grouped = $this->registry->grouped();

        $this->assertEquals('First', $grouped['main'][0]->label);
        $this->assertEquals('Second', $grouped['main'][1]->label);
        $this->assertEquals('Third', $grouped['main'][2]->label);
    }

    public function test_grouped_returns_groups_sorted_alphabetically(): void
    {
        $item1 = new NavigationItemDTO(label: 'Item 1', group: 'zebra', module: 'test');
        $item2 = new NavigationItemDTO(label: 'Item 2', group: 'alpha', module: 'test');
        $item3 = new NavigationItemDTO(label: 'Item 3', group: 'beta', module: 'test');

        $this->registry->register($item1);
        $this->registry->register($item2);
        $this->registry->register($item3);

        $grouped = $this->registry->grouped();
        $keys = array_keys($grouped);

        $this->assertEquals(['alpha', 'beta', 'zebra'], $keys);
    }

    public function test_sorted_returns_all_items_sorted_by_sort_order(): void
    {
        $item1 = new NavigationItemDTO(label: 'Last', sort: 100, module: 'test');
        $item2 = new NavigationItemDTO(label: 'First', sort: 10, module: 'test');
        $item3 = new NavigationItemDTO(label: 'Middle', sort: 50, module: 'test');

        $this->registry->register($item1);
        $this->registry->register($item2);
        $this->registry->register($item3);

        $sorted = $this->registry->sorted();

        $this->assertEquals('First', $sorted[0]->label);
        $this->assertEquals('Middle', $sorted[1]->label);
        $this->assertEquals('Last', $sorted[2]->label);
    }

    public function test_for_user_returns_items_without_permissions(): void
    {
        $publicItem = new NavigationItemDTO(label: 'Home', module: 'core');
        $restrictedItem = new NavigationItemDTO(
            label: 'Admin',
            permissions: ['admin-access'],
            module: 'admin',
        );

        $this->registry->register($publicItem);
        $this->registry->register($restrictedItem);

        $items = $this->registry->forUser([]);

        $this->assertCount(1, $items);
        $this->assertEquals('Home', $items[0]->label);
    }

    public function test_for_user_returns_items_with_matching_permissions(): void
    {
        $adminItem = new NavigationItemDTO(
            label: 'Admin Panel',
            permissions: ['admin-access'],
            module: 'admin',
        );
        $editorItem = new NavigationItemDTO(
            label: 'Editor',
            permissions: ['edit-content'],
            module: 'editor',
        );

        $this->registry->register($adminItem);
        $this->registry->register($editorItem);

        $items = $this->registry->forUser(['admin-access']);

        $this->assertCount(1, $items);
        $this->assertEquals('Admin Panel', $items[0]->label);
    }

    public function test_for_user_returns_items_with_any_matching_permission(): void
    {
        $item = new NavigationItemDTO(
            label: 'Content',
            permissions: ['view-content', 'edit-content', 'delete-content'],
            module: 'content',
        );

        $this->registry->register($item);

        $items = $this->registry->forUser(['edit-content']);

        $this->assertCount(1, $items);
    }

    public function test_for_user_excludes_items_without_matching_permissions(): void
    {
        $item1 = new NavigationItemDTO(
            label: 'Admin',
            permissions: ['admin-access'],
            module: 'admin',
        );
        $item2 = new NavigationItemDTO(
            label: 'Editor',
            permissions: ['editor-access'],
            module: 'editor',
        );

        $this->registry->register($item1);
        $this->registry->register($item2);

        $items = $this->registry->forUser(['viewer-access']);

        $this->assertEmpty($items);
    }

    public function test_for_user_includes_public_and_authorized_items(): void
    {
        $publicItem = new NavigationItemDTO(label: 'Home', module: 'core');
        $restrictedItem = new NavigationItemDTO(
            label: 'Dashboard',
            permissions: ['view-dashboard'],
            module: 'admin',
        );

        $this->registry->register($publicItem);
        $this->registry->register($restrictedItem);

        $items = $this->registry->forUser(['view-dashboard']);

        $this->assertCount(2, $items);
    }

    public function test_unregister_module_removes_all_items_for_module(): void
    {
        $blogItem1 = new NavigationItemDTO(label: 'Posts', module: 'blog');
        $blogItem2 = new NavigationItemDTO(label: 'Categories', module: 'blog');
        $forumItem = new NavigationItemDTO(label: 'Threads', module: 'forum');

        $this->registry->register($blogItem1);
        $this->registry->register($blogItem2);
        $this->registry->register($forumItem);

        $this->registry->unregisterModule('blog');

        $all = $this->registry->all();
        $this->assertCount(1, $all);
        $this->assertEquals('Threads', $all[0]->label);
    }

    public function test_unregister_module_maintains_indexed_array(): void
    {
        $item1 = new NavigationItemDTO(label: 'Item 1', module: 'test1');
        $item2 = new NavigationItemDTO(label: 'Item 2', module: 'test2');
        $item3 = new NavigationItemDTO(label: 'Item 3', module: 'test3');

        $this->registry->register($item1);
        $this->registry->register($item2);
        $this->registry->register($item3);

        $this->registry->unregisterModule('test2');

        $all = $this->registry->all();
        $this->assertArrayHasKey(0, $all);
        $this->assertArrayHasKey(1, $all);
        $this->assertArrayNotHasKey(2, $all);
    }

    public function test_clear_removes_all_items(): void
    {
        $item1 = new NavigationItemDTO(label: 'Item 1', module: 'test1');
        $item2 = new NavigationItemDTO(label: 'Item 2', module: 'test2');

        $this->registry->register($item1);
        $this->registry->register($item2);

        $this->registry->clear();

        $this->assertEmpty($this->registry->all());
    }

    public function test_for_module_returns_indexed_array(): void
    {
        $item1 = new NavigationItemDTO(label: 'Item 1', module: 'blog');
        $item2 = new NavigationItemDTO(label: 'Item 2', module: 'blog');

        $this->registry->register($item1);
        $this->registry->register($item2);

        $items = $this->registry->forModule('blog');

        $this->assertArrayHasKey(0, $items);
        $this->assertArrayHasKey(1, $items);
    }

    public function test_for_user_returns_indexed_array(): void
    {
        $item1 = new NavigationItemDTO(label: 'Public 1', module: 'core');
        $item2 = new NavigationItemDTO(label: 'Public 2', module: 'core');

        $this->registry->register($item1);
        $this->registry->register($item2);

        $items = $this->registry->forUser([]);

        $this->assertArrayHasKey(0, $items);
        $this->assertArrayHasKey(1, $items);
    }

    public function test_it_handles_items_with_default_group(): void
    {
        $item = new NavigationItemDTO(label: 'Default Item', module: 'test');

        $this->registry->register($item);

        $grouped = $this->registry->grouped();

        $this->assertArrayHasKey('default', $grouped);
        $this->assertCount(1, $grouped['default']);
    }

    public function test_it_handles_items_with_zero_sort_order(): void
    {
        $item1 = new NavigationItemDTO(label: 'Zero', sort: 0, module: 'test');
        $item2 = new NavigationItemDTO(label: 'One', sort: 1, module: 'test');

        $this->registry->register($item1);
        $this->registry->register($item2);

        $sorted = $this->registry->sorted();

        $this->assertEquals('Zero', $sorted[0]->label);
        $this->assertEquals('One', $sorted[1]->label);
    }
}
