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
    ],
];
