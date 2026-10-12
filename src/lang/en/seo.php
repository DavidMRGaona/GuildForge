<?php

declare(strict_types=1);

return [
    'formats' => [
        'date' => 'j F Y',
        'day_month' => 'j F',
    ],
    'schedule' => [
        'single_day' => ':date, :time',
        'same_month' => ':start_day–:end_date',
        'same_year' => ':start_day_month – :end_date',
        'different_year' => ':start_date – :end_date',
    ],
    'titles' => [
        'events' => 'Events',
        'articles' => 'Articles',
        'gallery' => 'Gallery',
        'calendar' => 'Event calendar',
        'search' => 'Search',
        'about' => 'About :name',
        'login' => 'Sign in',
        'register' => 'Create account',
        'forgot_password' => 'Forgot password',
        'reset_password' => 'Reset password',
        'verify_email' => 'Verify email',
        'profile' => 'My profile',
        'maintenance' => "We're under maintenance",
    ],
    'descriptions' => [
        'articles' => 'News and articles from the guild',
        'gallery' => 'Photo gallery of the guild',
    ],
];
