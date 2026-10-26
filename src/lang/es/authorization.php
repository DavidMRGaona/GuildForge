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
        'menu_items' => 'Menú de navegación',
        'settings' => 'Configuración',
        'mail' => 'Correo electrónico',
        'updates' => 'Actualizaciones',
        'admin' => 'Administración',
        'modules' => 'Módulos',
        'dashboard' => 'Escritorio',
        'pages' => 'Páginas',
    ],

    'actions' => [
        'view_any' => 'Ver listado',
        'view' => 'Ver detalle',
        'create' => 'Crear',
        'update' => 'Editar',
        'delete' => 'Eliminar',
        'manage' => 'Gestionar',
        'configure' => 'Configurar',
        'view_stats' => 'Ver estadísticas',
        'access' => 'Acceder',
        'maintenance' => 'Modo mantenimiento',
        'apply' => 'Aplicar',
        'assign_roles' => 'Asignar roles',
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
            'assign_roles' => 'Asignar roles a usuarios',
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
        'menu_items' => [
            'view_any' => 'Ver listado de elementos de menú',
            'view' => 'Ver detalle de elemento de menú',
            'create' => 'Crear elemento de menú',
            'update' => 'Editar y ordenar elementos de menú',
            'delete' => 'Eliminar elemento de menú',
        ],
        'settings' => [
            'manage' => 'Gestionar configuración del sitio',
            'maintenance' => 'Activar o desactivar el modo mantenimiento',
        ],
        'updates' => [
            'apply' => 'Aplicar actualizaciones',
        ],
        'mail' => [
            'configure' => 'Configurar correo electrónico',
            'view_stats' => 'Ver estadísticas de correo',
        ],
        'admin' => [
            'access' => 'Acceder al panel de administración',
        ],
        'modules' => [
            'manage' => 'Gestionar módulos',
            'configure' => 'Configurar módulos',
        ],
        'dashboard' => [
            'configure' => 'Configurar el escritorio',
        ],
        'pages' => [
            'manage' => 'Gestionar páginas legales y «Sobre nosotros»',
        ],
    ],

    'permission_descriptions' => [
        'settings' => [
            'manage' => 'Incluye elegir el rol de socio y la verificación de correo.',
        ],
        'users' => [
            'assign_roles' => 'Solo puede dar o quitar roles cuyos permisos tenga.',
        ],
        'modules' => [
            'manage' => 'Instalar, habilitar y deshabilitar módulos.',
            'configure' => 'Ajustes de los módulos habilitados. No permite instalarlos ni deshabilitarlos.',
        ],
        'pages' => [
            'manage' => 'Aviso legal, política de privacidad y «Sobre nosotros».',
        ],
        'admin' => [
            'access' => 'Entrar en el panel de administración.',
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

    'errors' => [
        'cannot_delete_base_role' => 'No se puede eliminar «:role»: es el rol base de todas las cuentas.',
        'cannot_restrict_role' => 'El rol «:role» no admite requisito de socio ni máximo de personas.',
    ],

    'member_role' => [
        'assigned' => '{1} :count usuario actualizado|[0,*] :count usuarios actualizados',
        'would_assign' => '{1} :count usuario se actualizaría|[0,*] :count usuarios se actualizarían',
        'missing' => 'No existe el rol «member»: créalo (o ejecuta los seeders) antes de lanzar este comando.',
    ],
];
