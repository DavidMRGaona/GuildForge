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
            'hero_image' => 'Hero image',
            'hero_image_help' => 'Background image for the hero. If not set, a gradient is shown.',
            'tagline' => 'Tagline',
            'tagline_help' => 'Optional subtitle under the hero title',
            'history' => 'Our history',
            'history_help' => 'Content for "Our history" section',
            'activities' => 'Activities',
            'activities_help' => 'Activities shown in "What we do"',
            'activity_icon' => 'Icon',
            'activity_title' => 'Title',
            'activity_description' => 'Description',
            'icons' => [
                'dice' => 'Dice (Role-playing games)',
                'sword' => 'Sword (Wargames)',
                'book' => 'Book (Reading/Lore)',
                'users' => 'Users (Community)',
                'calendar' => 'Calendar (Events)',
                'map' => 'Map (Campaigns)',
                'trophy' => 'Trophy (Tournaments)',
                'puzzle' => 'Puzzle (Board games)',
                'sparkles' => 'Sparkles (General)',
                'heart' => 'Heart (Volunteering)',
            ],
        ],
        'contact' => [
            'title' => 'Contact',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Postal address',
        ],
    ],
];
