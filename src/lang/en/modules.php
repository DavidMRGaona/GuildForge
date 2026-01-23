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
        'cannot_uninstall_with_dependents' => 'Cannot uninstall module :name because it is required by: :dependents.',
        'installation_failed' => 'Failed to install module: :error.',
        'invalid_zip' => 'The file is not a valid ZIP.',
        'zip_too_large' => 'The ZIP file exceeds the limit of :limit MB.',
        'manifest_not_found' => 'module.json not found in the ZIP file.',
        'invalid_manifest_json' => 'module.json is not a valid JSON file.',
        'missing_manifest_field' => 'The :field field is required in module.json.',
        'module_already_exists' => 'A module with the name :name already exists.',
        'forbidden_module_name' => 'The name :name is reserved and cannot be used.',
    ],

    // Filament admin interface
    'filament' => [
        'page' => [
            'title' => 'Module Management',
            'description' => 'Manage system modules: discover, install, enable/disable, and configure modules.',
            'navigation_label' => 'Modules',
            'navigation_group' => 'Administration',
        ],
        'filters' => [
            'all' => 'All',
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
        ],
        'search' => [
            'placeholder' => 'Search modules...',
        ],
        'actions' => [
            'discover' => 'Discover',
            'discover_tooltip' => 'Scan filesystem for new modules',
            'install' => 'Install',
            'install_tooltip' => 'Install a module from a ZIP file',
            'enable' => 'Enable',
            'enabling' => 'Enabling...',
            'disable' => 'Disable',
            'disabling' => 'Disabling...',
            'settings' => 'Settings',
            'uninstall' => 'Uninstall',
            'view_details' => 'View details',
            'hide_details' => 'Hide details',
        ],
        'card' => [
            'version' => 'Version',
            'author' => 'Author',
            'dependencies' => 'Dependencies',
            'no_dependencies' => 'No dependencies',
            'required_by' => 'Required by',
            'not_required' => 'Not required by other modules',
            'enabled_at' => 'Enabled on',
            'discovered_at' => 'Discovered on',
        ],
        'install_form' => [
            'title' => 'Install Module',
            'description' => 'Upload a ZIP file containing the module to install.',
            'file_label' => 'ZIP File',
            'file_help' => 'Maximum :size MB. Must contain a valid module.json file.',
            'submit' => 'Install',
            'cancel' => 'Cancel',
        ],
        'settings_page' => [
            'title' => ':name Settings',
            'back' => 'Back to modules',
            'no_settings' => 'This module has no configuration options.',
            'save' => 'Save settings',
            'saved' => 'Settings saved successfully.',
        ],
        'notifications' => [
            'discovered' => ':count module(s) discovered.',
            'no_new_modules' => 'No new modules found.',
            'enabled' => 'Module :name enabled successfully.',
            'disabled' => 'Module :name disabled successfully.',
            'installed' => 'Module :name installed successfully.',
            'uninstalled' => 'Module :name uninstalled successfully.',
            'cannot_enable' => 'Cannot enable module: :error',
            'cannot_disable' => 'Cannot disable module: :error',
            'cannot_uninstall' => 'Cannot uninstall module: :error',
        ],
        'confirm' => [
            'uninstall_title' => 'Uninstall module?',
            'uninstall_description' => 'This action will permanently delete the :name module and all its files. This action cannot be undone.',
            'uninstall_confirm' => 'Yes, uninstall',
            'uninstall_cancel' => 'Cancel',
        ],
        'empty' => [
            'title' => 'No modules',
            'description' => 'No modules found. Click "Discover" to scan the filesystem for modules or "Install" to add a new one.',
        ],
    ],
];
