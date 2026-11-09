<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Mail\Contracts\MailDeliveryRuleInterface;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Infrastructure\Mail\Listeners\FilterMailByPreferences;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Infrastructure\Mail\Preferences\MailCategoryRegistry;
use App\Infrastructure\Mail\Preferences\MailPreferenceService;
use App\Infrastructure\Mail\Preferences\Rules\CategoryPreferenceRule;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentMailPreferenceRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Mail categories and each person's choice of which optional mail they receive.
 */
final class MailPreferencesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Modules add theirs through ModuleServiceProvider::registerMailCategories(); a
        // malformed category fails loudly in local and testing, and is skipped elsewhere
        $this->app->singleton(MailCategoryRegistryInterface::class, function (Application $app): MailCategoryRegistry {
            $registry = new MailCategoryRegistry(rejectsInvalidCategories: $app->environment('local', 'testing'));
            CoreMailCategories::register($registry);

            return $registry;
        });

        $this->app->bind(MailPreferenceRepositoryInterface::class, EloquentMailPreferenceRepository::class);

        // Another reason to hold optional mail back is one more tagged rule
        $this->app->tag([CategoryPreferenceRule::class], MailDeliveryRuleInterface::TAG);
        $this->app->bind(MailPreferenceServiceInterface::class, fn (Application $app): MailPreferenceService => new MailPreferenceService(
            $app->make(MailCategoryRegistryInterface::class),
            $app->make(MailPreferenceRepositoryInterface::class),
            $app->tagged(MailDeliveryRuleInterface::TAG),
        ));

        $this->app->scoped(FilterMailByPreferences::class);
    }

    public function boot(): void
    {
        Event::listen(NotificationSending::class, FilterMailByPreferences::class);
    }
}
