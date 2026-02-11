<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Providers;

use App\Application\Mail\Services\MailConfigurationServiceInterface;
use Illuminate\Support\ServiceProvider;

class DynamicMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $mailConfigService = $this->app->make(MailConfigurationServiceInterface::class);
            $mailConfigService->applyToRuntime();
        } catch (\Throwable) {
            // Silent fallback to .env defaults when DB is unavailable
            // (e.g., during migrations, fresh install, or testing)
        }
    }
}
