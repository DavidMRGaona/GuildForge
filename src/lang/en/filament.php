<?php

declare(strict_types=1);

return [
    'settings' => [
        'title' => 'Site Settings',
        'location' => [
            'title' => 'Headquarters Location',
            'description' => 'Configure the coordinates and data of the location that will be displayed on the map on the "About" page.',
            'name' => 'Location Name',
            'address' => 'Address',
            'lat' => 'Latitude',
            'lng' => 'Longitude',
            'zoom' => 'Zoom Level',
            'preview' => 'Map Preview',
            'saved' => 'Settings saved successfully',
        ],
        'about' => [
            'title' => 'About us',
            'association_name' => 'Association name',
            'association_name_help' => 'Shown in the "About us" page title',
            'history' => 'Our history',
            'history_help' => 'Content for "Our history" section',
        ],
        'contact' => [
            'title' => 'Contact',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Postal address',
        ],
    ],
];
