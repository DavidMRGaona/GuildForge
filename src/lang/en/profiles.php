<?php

declare(strict_types=1);

return [
    'create_missing' => [
        'created' => '{1} :count profile created|[0,*] :count profiles created',
        'would_create' => '{1} :count profile would be created|[0,*] :count profiles would be created',
        'realigned' => '{1} :count address realigned with the public name|[0,*] :count addresses realigned with the public name',
        'would_realign' => '{1} :count address would be realigned with the public name|[0,*] :count addresses would be realigned with the public name',
        'failed' => '{1} :count account was left without a profile or not realigned (details in the log)|[0,*] :count accounts were left without a profile or not realigned (details in the log)',
        'would_fail' => '{1} :count account would be left without a free address|[0,*] :count accounts would be left without a free address',
        'dry_run_note' => 'Dry run: nothing was written. A clash with another write is only seen by a real run.',
    ],
    'stamp_completed' => [
        'stamped' => '{1} :count complete profile stamped|[0,*] :count complete profiles stamped',
        'would_stamp' => '{1} :count complete profile would be stamped|[0,*] :count complete profiles would be stamped',
        'dry_run_note' => 'Dry run: nothing was written.',
    ],
    'sections' => [
        'about' => [
            'label' => 'About me',
            'description' => 'What you tell about yourself, with the links you want to share.',
        ],
    ],
    'editor' => [
        'saved' => 'Profile saved.',
        'banner_saved' => 'Banner updated.',
        'banner_removed' => 'Banner removed.',
        'attributes' => [
            'handle' => 'address',
            'visibility' => 'who can see it',
            'bio' => 'about me',
            'show_membership' => 'show that I am a member',
            'sections' => 'sections',
            'section' => 'section',
            'stats' => 'counters',
            'stat' => 'counter',
            'banner' => 'banner',
        ],
        'errors' => [
            'handle_unavailable' => 'This address is not available.',
            'handle_malformed' => 'The address has 3 to 30 lowercase letters without accents, digits and single hyphens between them.',
            'handle_too_soon' => 'You can change your address again from :date.',
            'membership_only' => 'Only association members can show it.',
            'banner_type' => 'The banner must be a JPEG, PNG or WebP image.',
            'banner_size' => 'The banner can take 5 MB at most.',
            'banner_failed' => 'The banner could not be saved. Please try again.',
        ],
    ],
];
