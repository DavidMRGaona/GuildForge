<?php

declare(strict_types=1);

return [
    // Status labels
    'status' => [
        'enabled' => 'Habilitado',
        'disabled' => 'Deshabilitado',
    ],

    // Command messages
    'commands' => [
        'list' => [
            'title' => 'Módulos instalados',
            'empty' => 'No hay módulos instalados.',
            'columns' => [
                'name' => 'Nombre',
                'version' => 'Versión',
                'status' => 'Estado',
                'author' => 'Autor',
            ],
        ],
        'discover' => [
            'title' => 'Descubriendo módulos...',
            'found' => ':count módulo(s) descubierto(s).',
            'none' => 'No se encontraron módulos.',
            'success' => 'Módulo :name descubierto correctamente.',
        ],
        'enable' => [
            'title' => 'Habilitando módulo :name...',
            'success' => 'Módulo :name habilitado correctamente.',
            'already_enabled' => 'El módulo :name ya está habilitado.',
            'not_found' => 'Módulo :name no encontrado.',
            'dependency_error' => 'No se puede habilitar el módulo :name: :error',
        ],
        'disable' => [
            'title' => 'Deshabilitando módulo :name...',
            'success' => 'Módulo :name deshabilitado correctamente.',
            'already_disabled' => 'El módulo :name ya está deshabilitado.',
            'not_found' => 'Módulo :name no encontrado.',
            'dependency_error' => 'No se puede deshabilitar el módulo :name: :error',
        ],
        'migrate' => [
            'title' => 'Ejecutando migraciones para :name...',
            'success' => ':count migración(es) ejecutada(s) para :name.',
            'none' => 'No hay migraciones pendientes para :name.',
            'not_found' => 'Módulo :name no encontrado.',
        ],
    ],

    // Error messages
    'errors' => [
        'not_found' => 'Módulo :name no encontrado.',
        'already_enabled' => 'El módulo :name ya está habilitado.',
        'already_disabled' => 'El módulo :name ya está deshabilitado.',
        'missing_dependency' => 'El módulo :name requiere :dependency que no está disponible.',
        'version_mismatch' => 'El módulo :name requiere :dependency versión :required, pero la versión :current está instalada.',
        'dependent_modules' => 'No se puede deshabilitar el módulo :name porque está siendo utilizado por: :dependents.',
        'circular_dependency' => 'Se detectó una dependencia circular: :cycle.',
        'invalid_manifest' => 'El archivo module.json del módulo :name no es válido: :error.',
        'cannot_uninstall_with_dependents' => 'No se puede desinstalar el módulo :name porque está siendo utilizado por: :dependents.',
        'installation_failed' => 'Error al instalar el módulo: :error.',
        'invalid_zip' => 'El archivo no es un ZIP válido.',
        'zip_too_large' => 'El archivo ZIP excede el límite de :limit MB.',
        'manifest_not_found' => 'No se encontró el archivo module.json en el ZIP.',
        'invalid_manifest_json' => 'El archivo module.json no es un JSON válido.',
        'missing_manifest_field' => 'El campo :field es requerido en module.json.',
        'module_already_exists' => 'Ya existe un módulo con el nombre :name.',
        'forbidden_module_name' => 'El nombre :name está reservado y no puede usarse.',
    ],

    // Filament admin interface
    'filament' => [
        'page' => [
            'title' => 'Gestión de Módulos',
            'description' => 'Administra los módulos del sistema: descubre, instala, habilita/deshabilita y configura módulos.',
            'navigation_label' => 'Módulos',
            'navigation_group' => 'Administración',
        ],
        'filters' => [
            'all' => 'Todos',
            'enabled' => 'Habilitados',
            'disabled' => 'Deshabilitados',
        ],
        'search' => [
            'placeholder' => 'Buscar módulos...',
        ],
        'actions' => [
            'discover' => 'Descubrir',
            'discover_tooltip' => 'Buscar nuevos módulos en el sistema de archivos',
            'install' => 'Instalar',
            'install_tooltip' => 'Instalar un módulo desde un archivo ZIP',
            'enable' => 'Habilitar',
            'enabling' => 'Habilitando...',
            'disable' => 'Deshabilitar',
            'disabling' => 'Deshabilitando...',
            'settings' => 'Configuración',
            'uninstall' => 'Desinstalar',
            'view_details' => 'Ver detalles',
            'hide_details' => 'Ocultar detalles',
        ],
        'card' => [
            'version' => 'Versión',
            'author' => 'Autor',
            'dependencies' => 'Dependencias',
            'no_dependencies' => 'Sin dependencias',
            'required_by' => 'Requerido por',
            'not_required' => 'No es requerido por otros módulos',
            'enabled_at' => 'Habilitado el',
            'discovered_at' => 'Descubierto el',
        ],
        'install_form' => [
            'title' => 'Instalar Módulo',
            'description' => 'Sube un archivo ZIP con el módulo a instalar.',
            'file_label' => 'Archivo ZIP',
            'file_help' => 'Máximo :size MB. Debe contener un archivo module.json válido.',
            'submit' => 'Instalar',
            'cancel' => 'Cancelar',
        ],
        'settings_page' => [
            'title' => 'Configuración de :name',
            'back' => 'Volver a módulos',
            'no_settings' => 'Este módulo no tiene opciones de configuración.',
            'save' => 'Guardar configuración',
            'saved' => 'Configuración guardada correctamente.',
        ],
        'notifications' => [
            'discovered' => ':count módulo(s) descubierto(s).',
            'no_new_modules' => 'No se encontraron nuevos módulos.',
            'enabled' => 'Módulo ":name" habilitado correctamente.',
            'disabled' => 'Módulo ":name" deshabilitado correctamente.',
            'installed' => 'Módulo ":name" instalado correctamente.',
            'uninstalled' => 'Módulo ":name" desinstalado correctamente.',
            'cannot_install' => 'No se puede instalar el módulo: :error',
            'cannot_enable' => 'No se puede habilitar el módulo: :error',
            'cannot_disable' => 'No se puede deshabilitar el módulo: :error',
            'cannot_uninstall' => 'No se puede desinstalar el módulo: :error',
            'migrations_run' => 'Se ejecutaron :count migración(es).',
            'migrations_run_first_install' => 'Las migraciones se han ejecutado.',
            'data_deleted' => 'Los datos del módulo han sido eliminados.',
        ],
        'confirm' => [
            'uninstall_title' => '¿Desinstalar módulo?',
            'uninstall_description' => 'Esta acción eliminará permanentemente el módulo :name y todos sus archivos. Esta acción no se puede deshacer.',
            'uninstall_confirm' => 'Sí, desinstalar',
            'uninstall_cancel' => 'Cancelar',
            'delete_data_label' => 'Eliminar también los datos del módulo',
            'delete_data_help' => 'Si marcas esta opción, se eliminarán las tablas y datos creados por el módulo. Si no lo marcas, los datos se conservarán por si decides reinstalarlo en el futuro.',
        ],
        'empty' => [
            'title' => 'No hay módulos',
            'description' => 'No se encontraron módulos. Haz clic en "Descubrir" para buscar módulos en el sistema de archivos o "Instalar" para añadir uno nuevo.',
        ],
    ],
];
