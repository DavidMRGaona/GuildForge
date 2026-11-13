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
            'proposals' => 'Proposals',
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
            'proposals_review' => [
                'label' => 'Proposals to review',
                'description' => 'Notice when someone sends in an event or an article you can review.',
            ],
            'proposals_status' => [
                'label' => 'Status of my proposals',
                'description' => 'Notice when an event or an article you proposed is approved, returned or rejected.',
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
        'digest' => 'You receive this digest because you chose to get the notices of these categories once a day.',
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
    'digest' => [
        'subject' => '{1} Your daily digest: 1 notice|[2,*] Your daily digest: :count notices',
        'intro' => 'These are the notices you chose to receive together, once a day.',
        'open' => 'View',
        'more' => '{1} And 1 more notice, the oldest, that does not fit in this digest.|[2,*] And :count more notices, the oldest, that do not fit in this digest.',
    ],
];
