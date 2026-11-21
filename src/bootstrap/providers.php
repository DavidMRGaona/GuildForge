<?php

declare(strict_types=1);

use App\Infrastructure\Mail\Providers\DynamicMailServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ImpersonationServiceProvider;
use App\Providers\KnowledgeServiceProvider;
use App\Providers\LinkingServiceProvider;
use App\Providers\MailPreferencesServiceProvider;
use App\Providers\MediaServiceProvider;
use App\Providers\ProfilesServiceProvider;
use App\Providers\ProposalsServiceProvider;
use App\Providers\PublicationServiceProvider;
use App\Providers\RealtimeServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SiteBlocksServiceProvider;
use App\Providers\SiteNotificationsServiceProvider;
use App\Providers\SpreadsheetsServiceProvider;
use App\Providers\TwoFactorServiceProvider;
use App\Providers\WebAppServiceProvider;
use App\Providers\WebPushServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    DynamicMailServiceProvider::class,
    AdminPanelProvider::class,
    ImpersonationServiceProvider::class,
    KnowledgeServiceProvider::class,
    LinkingServiceProvider::class,
    MailPreferencesServiceProvider::class,
    MediaServiceProvider::class,
    ProfilesServiceProvider::class,
    ProposalsServiceProvider::class,
    PublicationServiceProvider::class,
    RealtimeServiceProvider::class,
    SearchServiceProvider::class,
    SiteBlocksServiceProvider::class,
    SiteNotificationsServiceProvider::class,
    SpreadsheetsServiceProvider::class,
    TwoFactorServiceProvider::class,
    WebAppServiceProvider::class,
    WebPushServiceProvider::class,
];
