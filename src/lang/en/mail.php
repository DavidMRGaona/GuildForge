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
    'footer' => [
        'optional' => "Don't want to receive more emails like this?",
        'unsubscribe' => 'Unsubscribe from «:category»',
        'choose' => 'Choose which emails you receive',
        'mandatory' => 'This email is always sent. You can choose which others you receive :profile.',
        'without_category' => 'You can choose which emails you receive :profile.',
        'profile_link' => 'in your profile',
        'mute_subject' => 'Stop notices about «:subject»',
    ],
    'unsubscribe' => [
        'done' => 'You will no longer receive emails of «:category».',
        'resubscribed' => 'You will receive emails of «:category» again.',
        'mandatory' => 'Emails of «:category» are always sent.',
        'one_click_not_found' => 'This link can no longer be used to unsubscribe.',
    ],
    'mute_subject' => [
        'unmuted' => 'You will receive notices about «:subject» again.',
    ],
];
