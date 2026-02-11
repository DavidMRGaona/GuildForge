<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthorizationServiceProvider::class,
    App\Infrastructure\Mail\Providers\DynamicMailServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
];
