<?php

declare(strict_types=1);

use App\Infrastructure\Mail\Providers\DynamicMailServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    DynamicMailServiceProvider::class,
    AdminPanelProvider::class,
];
