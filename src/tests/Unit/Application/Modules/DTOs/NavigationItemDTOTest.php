<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Modules\DTOs;

use App\Application\Modules\DTOs\NavigationItemDTO;
use PHPUnit\Framework\TestCase;

final class NavigationItemDTOTest extends TestCase
{
    public function test_it_creates_navigation_item_from_constructor_with_all_properties(): void
    {
        $dto = new NavigationItemDTO(
            label: 'Dashboard',
            route: 'admin.dashboard',
            url: null,
            icon: 'heroicon-o-home',
            group: 'main',
            sort: 10,
            children: [],
            permissions: ['view-dashboard'],
            module: 'admin',
            badge: '5',
            badgeColor: 'danger',
        );

        $this->assertEquals('Dashboard', $dto->label);
        $this->assertEquals('admin.dashboard', $dto->route);
        $this->assertNull($dto->url);
        $this->assertEquals('heroicon-o-home', $dto->icon);
        $this->assertEquals('main', $dto->group);
        $this->assertEquals(10, $dto->sort);
        $this->assertEmpty($dto->children);
        $this->assertEquals(['view-dashboard'], $dto->permissions);
        $this->assertEquals('admin', $dto->module);
        $this->assertEquals('5', $dto->badge);
        $this->assertEquals('danger', $dto->badgeColor);
    }

    public function test_it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'label' => 'Events',
            'route' => 'events.index',
            'url' => null,
            'icon' => 'heroicon-o-calendar',
            'group' => 'content',
            'sort' => 20,
            'children' => [],
            'permissions' => ['manage-events'],
            'module' => 'events',
            'badge' => '3',
            'badgeColor' => 'success',
        ];

        $dto = NavigationItemDTO::fromArray($data);

        $this->assertEquals('Events', $dto->label);
        $this->assertEquals('events.index', $dto->route);
        $this->assertNull($dto->url);
        $this->assertEquals('heroicon-o-calendar', $dto->icon);
        $this->assertEquals('content', $dto->group);
        $this->assertEquals(20, $dto->sort);
        $this->assertEmpty($dto->children);
        $this->assertEquals(['manage-events'], $dto->permissions);
        $this->assertEquals('events', $dto->module);
        $this->assertEquals('3', $dto->badge);
        $this->assertEquals('success', $dto->badgeColor);
    }

    public function test_it_creates_from_array_with_minimal_fields(): void
    {
        $data = [
            'label' => 'Home',
        ];

        $dto = NavigationItemDTO::fromArray($data);

        $this->assertEquals('Home', $dto->label);
        $this->assertNull($dto->route);
        $this->assertNull($dto->url);
        $this->assertNull($dto->icon);
        $this->assertEquals('default', $dto->group);
        $this->assertEquals(0, $dto->sort);
        $this->assertEmpty($dto->children);
        $this->assertEmpty($dto->permissions);
        $this->assertNull($dto->module);
        $this->assertNull($dto->badge);
        $this->assertNull($dto->badgeColor);
    }

    public function test_it_creates_from_array_with_nested_children(): void
    {
        $data = [
            'label' => 'Settings',
            'icon' => 'heroicon-o-cog',
            'children' => [
                [
                    'label' => 'General',
                    'route' => 'settings.general',
                ],
                [
                    'label' => 'Security',
                    'route' => 'settings.security',
                    'children' => [
                        [
                            'label' => 'Two-Factor',
                            'route' => 'settings.security.2fa',
                        ],
                    ],
                ],
            ],
        ];

        $dto = NavigationItemDTO::fromArray($data);

        $this->assertEquals('Settings', $dto->label);
        $this->assertCount(2, $dto->children);
        $this->assertEquals('General', $dto->children[0]->label);
        $this->assertEquals('settings.general', $dto->children[0]->route);
        $this->assertEquals('Security', $dto->children[1]->label);
        $this->assertCount(1, $dto->children[1]->children);
        $this->assertEquals('Two-Factor', $dto->children[1]->children[0]->label);
    }

    public function test_to_array_returns_correct_representation(): void
    {
        $dto = new NavigationItemDTO(
            label: 'Articles',
            route: 'articles.index',
            icon: 'heroicon-o-document-text',
            group: 'content',
            sort: 15,
            permissions: ['view-articles', 'edit-articles'],
            module: 'articles',
        );

        $array = $dto->toArray();

        $expected = [
            'label' => 'Articles',
            'route' => 'articles.index',
            'url' => null,
            'icon' => 'heroicon-o-document-text',
            'group' => 'content',
            'sort' => 15,
            'children' => [],
            'permissions' => ['view-articles', 'edit-articles'],
            'module' => 'articles',
            'badge' => null,
            'badgeColor' => null,
        ];

        $this->assertEquals($expected, $array);
    }

    public function test_to_array_includes_nested_children(): void
    {
        $child = new NavigationItemDTO(
            label: 'Child',
            route: 'child.route',
        );

        $parent = new NavigationItemDTO(
            label: 'Parent',
            children: [$child],
        );

        $array = $parent->toArray();

        $this->assertCount(1, $array['children']);
        $this->assertEquals('Child', $array['children'][0]['label']);
        $this->assertEquals('child.route', $array['children'][0]['route']);
    }

    public function test_has_children_returns_true_when_children_present(): void
    {
        $child = new NavigationItemDTO(label: 'Child');
        $dto = new NavigationItemDTO(label: 'Parent', children: [$child]);

        $this->assertTrue($dto->hasChildren());
    }

    public function test_has_children_returns_false_when_no_children(): void
    {
        $dto = new NavigationItemDTO(label: 'Item');

        $this->assertFalse($dto->hasChildren());
    }

    public function test_requires_permission_returns_true_when_permissions_present(): void
    {
        $dto = new NavigationItemDTO(
            label: 'Admin',
            permissions: ['admin-access'],
        );

        $this->assertTrue($dto->requiresPermission());
    }

    public function test_requires_permission_returns_false_when_no_permissions(): void
    {
        $dto = new NavigationItemDTO(label: 'Public');

        $this->assertFalse($dto->requiresPermission());
    }

    public function test_it_handles_multiple_permissions(): void
    {
        $dto = new NavigationItemDTO(
            label: 'Users',
            permissions: ['view-users', 'edit-users', 'delete-users'],
        );

        $this->assertTrue($dto->requiresPermission());
        $this->assertCount(3, $dto->permissions);
    }

    public function test_it_supports_url_instead_of_route(): void
    {
        $dto = new NavigationItemDTO(
            label: 'External Link',
            url: 'https://example.com',
        );

        $this->assertNull($dto->route);
        $this->assertEquals('https://example.com', $dto->url);
    }

    public function test_it_uses_default_group_when_not_specified(): void
    {
        $dto = NavigationItemDTO::fromArray(['label' => 'Item']);

        $this->assertEquals('default', $dto->group);
    }

    public function test_it_uses_default_sort_when_not_specified(): void
    {
        $dto = NavigationItemDTO::fromArray(['label' => 'Item']);

        $this->assertEquals(0, $dto->sort);
    }

    public function test_deeply_nested_children_are_converted_correctly(): void
    {
        $data = [
            'label' => 'Level 1',
            'children' => [
                [
                    'label' => 'Level 2',
                    'children' => [
                        [
                            'label' => 'Level 3',
                            'route' => 'deep.route',
                        ],
                    ],
                ],
            ],
        ];

        $dto = NavigationItemDTO::fromArray($data);
        $array = $dto->toArray();

        $this->assertEquals('Level 1', $array['label']);
        $this->assertEquals('Level 2', $array['children'][0]['label']);
        $this->assertEquals('Level 3', $array['children'][0]['children'][0]['label']);
        $this->assertEquals('deep.route', $array['children'][0]['children'][0]['route']);
    }
}
