<?php

declare(strict_types=1);

return [
    'tabs' => [
        'content' => 'Content',
        'users' => 'Users and roles',
        'site' => 'Site',
    ],
    'resources' => [
        'events' => 'Events',
        'articles' => 'Articles',
        'galleries' => 'Galleries',
        'users' => 'Users',
        'roles' => 'Roles',
        'hero_slides' => 'Hero slides',
        'tags' => 'Tags',
        'menu_items' => 'Navigation menu',
        'settings' => 'Settings',
        'mail' => 'Email',
        'updates' => 'Updates',
        'admin' => 'Administration',
        'modules' => 'Modules',
        'dashboard' => 'Dashboard',
        'pages' => 'Pages',
    ],

    'actions' => [
        'view_any' => 'View list',
        'view' => 'View detail',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
        'manage' => 'Manage',
        'configure' => 'Configure',
        'view_stats' => 'View statistics',
        'access' => 'Access',
        'maintenance' => 'Maintenance mode',
        'apply' => 'Apply',
        'assign_roles' => 'Assign roles',
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
            'assign_roles' => 'Assign roles to users',
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
        'menu_items' => [
            'view_any' => 'View menu items list',
            'view' => 'View menu item details',
            'create' => 'Create menu item',
            'update' => 'Edit and reorder menu items',
            'delete' => 'Delete menu item',
        ],
        'settings' => [
            'manage' => 'Manage site settings',
            'maintenance' => 'Turn maintenance mode on or off',
        ],
        'updates' => [
            'apply' => 'Apply updates',
        ],
        'mail' => [
            'configure' => 'Configure email',
            'view_stats' => 'View email statistics',
        ],
        'admin' => [
            'access' => 'Access admin panel',
        ],
        'modules' => [
            'manage' => 'Manage modules',
            'configure' => 'Configure modules',
        ],
        'dashboard' => [
            'configure' => 'Configure the dashboard',
        ],
        'pages' => [
            'manage' => 'Manage legal pages and "About us"',
        ],
    ],

    'permission_descriptions' => [
        'settings' => [
            'manage' => 'Includes choosing the association member role and email verification.',
        ],
        'users' => [
            'assign_roles' => 'Can only give or remove roles whose permissions they hold.',
        ],
        'modules' => [
            'manage' => 'Install, enable and disable modules.',
            'configure' => 'Settings of enabled modules. Does not allow installing or disabling them.',
        ],
        'pages' => [
            'manage' => 'Legal notice, privacy policy and "About us".',
        ],
        'admin' => [
            'access' => 'Enter the admin panel.',
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

    'errors' => [
        'cannot_delete_base_role' => 'Cannot delete ":role": it is the base role of every account.',
        'cannot_restrict_role' => 'The ":role" role does not accept an association member requirement or a maximum number of people.',
        'role_holder_limit' => '[0,1] The role «:role» is already held by :holders. Take it from them before giving it to someone else.|[2,*] The role «:role» is already held by :holders. Take it from one of them before giving it to someone else.',
        'role_grant_denied' => 'You cannot give or take these roles: :roles.',
    ],

    'member_role' => [
        'assigned' => '{1} :count user updated|[0,*] :count users updated',
        'would_assign' => '{1} :count user would be updated|[0,*] :count users would be updated',
        'missing' => 'The "member" role does not exist: create it (or run the seeders) before running this command.',
    ],
];
