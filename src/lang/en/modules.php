<?php

declare(strict_types=1);

return [
    // Status labels
    'status' => [
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
    ],

    // Command messages
    'commands' => [
        'list' => [
            'title' => 'Installed Modules',
            'empty' => 'No modules installed.',
            'columns' => [
                'name' => 'Name',
                'version' => 'Version',
                'status' => 'Status',
                'author' => 'Author',
            ],
        ],
        'discover' => [
            'title' => 'Discovering modules...',
            'found' => ':count module(s) discovered.',
            'none' => 'No modules found.',
            'success' => 'Module :name discovered successfully.',
        ],
        'enable' => [
            'title' => 'Enabling module :name...',
            'success' => 'Module :name enabled successfully.',
            'already_enabled' => 'Module :name is already enabled.',
            'not_found' => 'Module :name not found.',
            'dependency_error' => 'Cannot enable module :name: :error',
        ],
        'disable' => [
            'title' => 'Disabling module :name...',
            'success' => 'Module :name disabled successfully.',
            'already_disabled' => 'Module :name is already disabled.',
            'not_found' => 'Module :name not found.',
            'dependency_error' => 'Cannot disable module :name: :error',
        ],
        'migrate' => [
            'title' => 'Running migrations for :name...',
            'success' => ':count migration(s) executed for :name.',
            'none' => 'No pending migrations for :name.',
            'not_found' => 'Module :name not found.',
        ],
    ],

    // Error messages
    'errors' => [
        'not_found' => 'Module :name not found.',
        'already_enabled' => 'Module :name is already enabled.',
        'already_disabled' => 'Module :name is already disabled.',
        'missing_dependency' => 'Module :name requires :dependency which is not available.',
        'version_mismatch' => 'Module :name requires :dependency version :required, but version :current is installed.',
        'dependent_modules' => 'Cannot disable module :name because it is required by: :dependents.',
        'circular_dependency' => 'Circular dependency detected: :cycle.',
        'invalid_manifest' => 'Invalid module.json for module :name: :error.',
    ],
];
