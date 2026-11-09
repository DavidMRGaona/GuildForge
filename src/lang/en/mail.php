<?php

declare(strict_types=1);

return [
    'test' => [
        'subject' => ':app: test email',
        'heading' => 'Test email',
        'intro' => 'This is a test email sent from :app to check the mail configuration.',
        'driver' => 'Driver',
        'date' => 'Date',
        'outro' => 'If you received this email, the mail configuration works.',
    ],
    'preferences' => [
        'sections' => [
            'account' => 'Account',
            'site_management' => 'Site management',
        ],
        'categories' => [
            'account' => [
                'label' => 'Your account',
                'description' => 'Email verification, password or email change, welcome and notices about your roles.',
                'reason' => 'Always sent: it confirms something you did, tells you about a change to your account or asks you to act.',
            ],
            'role_management' => [
                'label' => 'Inactive roles',
                'description' => 'Notice when someone you can grant roles to is left with an inactive one.',
            ],
        ],
    ],
];
