<?php

declare(strict_types=1);

return [
    'resources' => [
        'events' => 'Events',
        'articles' => 'Articles',
        'galleries' => 'Galleries',
        'users' => 'Users',
        'roles' => 'Roles',
        'hero_slides' => 'Hero slides',
        'tags' => 'Tags',
        'settings' => 'Settings',
        'admin' => 'Administration',
    ],

    'actions' => [
        'view_any' => 'View list',
        'view' => 'View detail',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
        'manage' => 'Manage',
        'access' => 'Access',
    ],

    'permissions' => [
        'events' => [
            'view_any' => 'View events list',
            'view' => 'View event detail',
            'create' => 'Create event',
            'update' => 'Edit event',
            'delete' => 'Delete event',
        ],
        'articles' => [
            'view_any' => 'View articles list',
            'view' => 'View article detail',
            'create' => 'Create article',
            'update' => 'Edit article',
            'delete' => 'Delete article',
        ],
        'galleries' => [
            'view_any' => 'View galleries list',
            'view' => 'View gallery detail',
            'create' => 'Create gallery',
            'update' => 'Edit gallery',
            'delete' => 'Delete gallery',
        ],
        'users' => [
            'view_any' => 'View users list',
            'view' => 'View user detail',
            'create' => 'Create user',
            'update' => 'Edit user',
            'delete' => 'Delete user',
        ],
        'roles' => [
            'view_any' => 'View roles list',
            'view' => 'View role detail',
            'create' => 'Create role',
            'update' => 'Edit role',
            'delete' => 'Delete role',
        ],
        'hero_slides' => [
            'view_any' => 'View slides list',
            'create' => 'Create slide',
            'update' => 'Edit slide',
            'delete' => 'Delete slide',
        ],
        'tags' => [
            'view_any' => 'View tags list',
            'create' => 'Create tag',
            'update' => 'Edit tag',
            'delete' => 'Delete tag',
        ],
        'settings' => [
            'manage' => 'Manage site settings',
        ],
        'admin' => [
            'access' => 'Access admin panel',
        ],
    ],

    'messages' => [
        'sync_started' => 'Syncing permissions...',
        'sync_completed' => 'Permissions synced successfully.',
        'permissions_created' => ':count permission(s) created.',
        'permissions_updated' => ':count permission(s) updated.',
        'roles_created' => ':count role(s) created.',
        'permissions_assigned' => 'Permissions assigned to default roles.',
        'no_changes' => 'No changes made.',
    ],
];
