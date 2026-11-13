<?php

declare(strict_types=1);

use App\Infrastructure\Mail\Providers\DynamicMailServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ImpersonationServiceProvider;
use App\Providers\KnowledgeServiceProvider;
use App\Providers\MailPreferencesServiceProvider;
use App\Providers\MediaServiceProvider;
use App\Providers\PublicationServiceProvider;
use App\Providers\RealtimeServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SiteNotificationsServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    DynamicMailServiceProvider::class,
    AdminPanelProvider::class,
    ImpersonationServiceProvider::class,
    KnowledgeServiceProvider::class,
    MailPreferencesServiceProvider::class,
    MediaServiceProvider::class,
    PublicationServiceProvider::class,
    RealtimeServiceProvider::class,
    SearchServiceProvider::class,
    SiteNotificationsServiceProvider::class,
];
