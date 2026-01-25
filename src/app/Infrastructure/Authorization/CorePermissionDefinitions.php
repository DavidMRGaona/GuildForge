<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;

final class CorePermissionDefinitions
{
    /**
     * Get all core permission definitions.
     *
     * @return array<PermissionDefinitionDTO>
     */
    public static function all(): array
    {
        return [
            // Events
            new PermissionDefinitionDTO(
                key: 'events.view_any',
                label: __('authorization.permissions.events.view_any'),
                resource: 'events',
                action: 'view_any',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'events.view',
                label: __('authorization.permissions.events.view'),
                resource: 'events',
                action: 'view',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'events.create',
                label: __('authorization.permissions.events.create'),
                resource: 'events',
                action: 'create',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'events.update',
                label: __('authorization.permissions.events.update'),
                resource: 'events',
                action: 'update',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'events.delete',
                label: __('authorization.permissions.events.delete'),
                resource: 'events',
                action: 'delete',
                defaultRoles: ['editor'],
            ),

            // Articles
            new PermissionDefinitionDTO(
                key: 'articles.view_any',
                label: __('authorization.permissions.articles.view_any'),
                resource: 'articles',
                action: 'view_any',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'articles.view',
                label: __('authorization.permissions.articles.view'),
                resource: 'articles',
                action: 'view',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'articles.create',
                label: __('authorization.permissions.articles.create'),
                resource: 'articles',
                action: 'create',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'articles.update',
                label: __('authorization.permissions.articles.update'),
                resource: 'articles',
                action: 'update',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'articles.delete',
                label: __('authorization.permissions.articles.delete'),
                resource: 'articles',
                action: 'delete',
                defaultRoles: ['editor'],
            ),

            // Galleries
            new PermissionDefinitionDTO(
                key: 'galleries.view_any',
                label: __('authorization.permissions.galleries.view_any'),
                resource: 'galleries',
                action: 'view_any',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'galleries.view',
                label: __('authorization.permissions.galleries.view'),
                resource: 'galleries',
                action: 'view',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'galleries.create',
                label: __('authorization.permissions.galleries.create'),
                resource: 'galleries',
                action: 'create',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'galleries.update',
                label: __('authorization.permissions.galleries.update'),
                resource: 'galleries',
                action: 'update',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'galleries.delete',
                label: __('authorization.permissions.galleries.delete'),
                resource: 'galleries',
                action: 'delete',
                defaultRoles: ['editor'],
            ),

            // Users
            new PermissionDefinitionDTO(
                key: 'users.view_any',
                label: __('authorization.permissions.users.view_any'),
                resource: 'users',
                action: 'view_any',
            ),
            new PermissionDefinitionDTO(
                key: 'users.view',
                label: __('authorization.permissions.users.view'),
                resource: 'users',
                action: 'view',
            ),
            new PermissionDefinitionDTO(
                key: 'users.create',
                label: __('authorization.permissions.users.create'),
                resource: 'users',
                action: 'create',
            ),
            new PermissionDefinitionDTO(
                key: 'users.update',
                label: __('authorization.permissions.users.update'),
                resource: 'users',
                action: 'update',
            ),
            new PermissionDefinitionDTO(
                key: 'users.delete',
                label: __('authorization.permissions.users.delete'),
                resource: 'users',
                action: 'delete',
            ),

            // Roles
            new PermissionDefinitionDTO(
                key: 'roles.view_any',
                label: __('authorization.permissions.roles.view_any'),
                resource: 'roles',
                action: 'view_any',
            ),
            new PermissionDefinitionDTO(
                key: 'roles.view',
                label: __('authorization.permissions.roles.view'),
                resource: 'roles',
                action: 'view',
            ),
            new PermissionDefinitionDTO(
                key: 'roles.create',
                label: __('authorization.permissions.roles.create'),
                resource: 'roles',
                action: 'create',
            ),
            new PermissionDefinitionDTO(
                key: 'roles.update',
                label: __('authorization.permissions.roles.update'),
                resource: 'roles',
                action: 'update',
            ),
            new PermissionDefinitionDTO(
                key: 'roles.delete',
                label: __('authorization.permissions.roles.delete'),
                resource: 'roles',
                action: 'delete',
            ),

            // Hero slides
            new PermissionDefinitionDTO(
                key: 'hero_slides.view_any',
                label: __('authorization.permissions.hero_slides.view_any'),
                resource: 'hero_slides',
                action: 'view_any',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'hero_slides.create',
                label: __('authorization.permissions.hero_slides.create'),
                resource: 'hero_slides',
                action: 'create',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'hero_slides.update',
                label: __('authorization.permissions.hero_slides.update'),
                resource: 'hero_slides',
                action: 'update',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'hero_slides.delete',
                label: __('authorization.permissions.hero_slides.delete'),
                resource: 'hero_slides',
                action: 'delete',
                defaultRoles: ['editor'],
            ),

            // Tags
            new PermissionDefinitionDTO(
                key: 'tags.view_any',
                label: __('authorization.permissions.tags.view_any'),
                resource: 'tags',
                action: 'view_any',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'tags.create',
                label: __('authorization.permissions.tags.create'),
                resource: 'tags',
                action: 'create',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'tags.update',
                label: __('authorization.permissions.tags.update'),
                resource: 'tags',
                action: 'update',
                defaultRoles: ['editor'],
            ),
            new PermissionDefinitionDTO(
                key: 'tags.delete',
                label: __('authorization.permissions.tags.delete'),
                resource: 'tags',
                action: 'delete',
                defaultRoles: ['editor'],
            ),

            // Settings
            new PermissionDefinitionDTO(
                key: 'settings.manage',
                label: __('authorization.permissions.settings.manage'),
                resource: 'settings',
                action: 'manage',
            ),

            // Admin panel access
            new PermissionDefinitionDTO(
                key: 'admin.access',
                label: __('authorization.permissions.admin.access'),
                resource: 'admin',
                action: 'access',
                defaultRoles: ['editor'],
            ),
        ];
    }

    /**
     * Get permission definitions grouped by resource.
     *
     * @return array<string, array<PermissionDefinitionDTO>>
     */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::all() as $permission) {
            $grouped[$permission->resource][] = $permission;
        }

        return $grouped;
    }
}
