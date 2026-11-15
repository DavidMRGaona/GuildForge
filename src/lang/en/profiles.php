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
    'sections' => [
        'about' => [
            'label' => 'About me',
            'description' => 'What you tell about yourself, with the links you want to share.',
        ],
    ],
];
