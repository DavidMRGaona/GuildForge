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
        'reset' => [
            'subject' => 'The administrators removed two-factor authentication from your :site account',
            'body' => 'The administrators of :site removed two-factor authentication from your account on :when. Every open session of your account has been closed. Your password is enough to sign in; you can turn two-factor authentication on again from your account settings.',
            'not_you' => 'If you did not ask for this, change your password as soon as possible and tell the site\'s administrators.',
        ],
        'repeated_code_failures' => [
            'subject' => 'Attempts to sign in to your :site account',
            'body' => 'Someone tried to sign in to your :site account with your password and a wrong code several times in a row (the last one on :when). Two-factor authentication stopped them.',
            'not_you' => 'If it was not you, someone knows your password: change it as soon as possible.',
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

    'command' => [
        'not_found' => 'There is no account with that email.',
        'confirm' => 'Remove the two-factor authentication of :email (:name, id :id)?',
        'ambiguous' => 'There are :count accounts whose email differs only in capitals: type the exact email of the one you mean.',
        'cancelled' => 'Cancelled: nothing was changed.',
        'refused' => 'Two-factor authentication could not be reset.',
        'nothing' => 'The account :email has no two-factor authentication on: nothing to remove.',
        'done' => 'Two-factor authentication removed from :email. Its sessions were closed and the person was mailed.',
    ],

    // The panel's sign-in challenge (core#770)
    'panel' => [
        'label' => 'Authenticator app',
        'app_code' => 'App code',
        'recovery_code' => 'Recovery code',
        'use_recovery_code' => 'Use a recovery code',
    ],
];
