<?php

declare(strict_types=1);

return [
    'unavailable' => 'Two-factor authentication is unavailable right now. Try again in a few seconds.',
    'mail' => [
        'enabled' => [
            'subject' => 'Two-factor authentication turned on at :site',
            'body' => 'Two-factor authentication was turned on for your :site account on :when. From now on, every sign-in asks for a code from your authenticator app. Your account\'s other open sessions have been closed.',
        ],
        'disabled' => [
            'subject' => 'Two-factor authentication turned off at :site',
            'body' => 'Two-factor authentication was turned off for your :site account on :when. Your password is enough to sign in again.',
        ],
        'recovery_code_used' => [
            'subject' => 'A recovery code was used at :site',
            'body' => 'One of your recovery codes was used to sign in to your :site account on :when.',
            'remaining' => '{0} You have no recovery codes left.|{1} You have :count recovery code left.|[2,*] You have :count recovery codes left.',
            'few_left' => 'You are running low: generate new codes from your account settings and keep them somewhere safe.',
        ],
        'recovery_codes_regenerated' => [
            'subject' => 'New recovery codes at :site',
            'body' => 'New recovery codes were generated for your :site account on :when. The previous ones no longer work.',
        ],
        'not_you' => 'If it was not you, change your password as soon as possible and tell the site\'s administrators.',
        'action' => 'Go to your account settings',
    ],
];
