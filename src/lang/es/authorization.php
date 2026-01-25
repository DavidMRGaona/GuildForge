<?php

declare(strict_types=1);

return [
    'resources' => [
        'events' => 'Eventos',
        'articles' => 'Artículos',
        'galleries' => 'Galerías',
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'hero_slides' => 'Slides del hero',
        'tags' => 'Etiquetas',
        'settings' => 'Configuración',
        'admin' => 'Administración',
    ],

    'actions' => [
        'view_any' => 'Ver listado',
        'view' => 'Ver detalle',
        'create' => 'Crear',
        'update' => 'Editar',
        'delete' => 'Eliminar',
        'manage' => 'Gestionar',
        'access' => 'Acceder',
    ],

    'permissions' => [
        'events' => [
            'view_any' => 'Ver listado de eventos',
            'view' => 'Ver detalle de evento',
            'create' => 'Crear evento',
            'update' => 'Editar evento',
            'delete' => 'Eliminar evento',
        ],
        'articles' => [
            'view_any' => 'Ver listado de artículos',
            'view' => 'Ver detalle de artículo',
            'create' => 'Crear artículo',
            'update' => 'Editar artículo',
            'delete' => 'Eliminar artículo',
        ],
        'galleries' => [
            'view_any' => 'Ver listado de galerías',
            'view' => 'Ver detalle de galería',
            'create' => 'Crear galería',
            'update' => 'Editar galería',
            'delete' => 'Eliminar galería',
        ],
        'users' => [
            'view_any' => 'Ver listado de usuarios',
            'view' => 'Ver detalle de usuario',
            'create' => 'Crear usuario',
            'update' => 'Editar usuario',
            'delete' => 'Eliminar usuario',
        ],
        'roles' => [
            'view_any' => 'Ver listado de roles',
            'view' => 'Ver detalle de rol',
            'create' => 'Crear rol',
            'update' => 'Editar rol',
            'delete' => 'Eliminar rol',
        ],
        'hero_slides' => [
            'view_any' => 'Ver listado de slides',
            'create' => 'Crear slide',
            'update' => 'Editar slide',
            'delete' => 'Eliminar slide',
        ],
        'tags' => [
            'view_any' => 'Ver listado de etiquetas',
            'create' => 'Crear etiqueta',
            'update' => 'Editar etiqueta',
            'delete' => 'Eliminar etiqueta',
        ],
        'settings' => [
            'manage' => 'Gestionar configuración del sitio',
        ],
        'admin' => [
            'access' => 'Acceder al panel de administración',
        ],
    ],

    'messages' => [
        'sync_started' => 'Sincronizando permisos...',
        'sync_completed' => 'Permisos sincronizados correctamente.',
        'permissions_created' => ':count permiso(s) creado(s).',
        'permissions_updated' => ':count permiso(s) actualizado(s).',
        'roles_created' => ':count rol(es) creado(s).',
        'permissions_assigned' => 'Permisos asignados a roles por defecto.',
        'no_changes' => 'No se realizaron cambios.',
    ],
];
