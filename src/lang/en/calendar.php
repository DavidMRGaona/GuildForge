<?php

declare(strict_types=1);

return [
    'sources' => [
        'event' => 'Events',
    ],
    'public' => [
        'feed_name' => 'Calendar · :site',
    ],
    'personal' => [
        'feed_name' => 'My calendar · :site',
        'sign_in_required' => 'Sign in to see only your own entries.',
    ],
];
