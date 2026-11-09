<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Mail\Contracts\MailDeliveryRuleInterface;
use App\Application\Mail\Services\MailCategoryOptOutStatisticsInterface;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Domain\Mail\Repositories\MailPauseRepositoryInterface;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\Mail\Repositories\MailSubjectMuteRepositoryInterface;
use App\Infrastructure\Mail\Channels\PreferenceAwareMailChannel;
use App\Infrastructure\Mail\Footer\CurrentMailFooter;
use App\Infrastructure\Mail\Listeners\FilterMailByPreferences;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Infrastructure\Mail\Preferences\MailCategoryOptOutStatistics;
use App\Infrastructure\Mail\Preferences\MailCategoryRegistry;
use App\Infrastructure\Mail\Preferences\MailPauseService;
use App\Infrastructure\Mail\Preferences\MailPreferenceService;
use App\Infrastructure\Mail\Preferences\MailSubjectMuteService;
use App\Infrastructure\Mail\Preferences\Rules\CategoryPreferenceRule;
use App\Infrastructure\Mail\Preferences\Rules\MutedSubjectRule;
use App\Infrastructure\Mail\Preferences\Rules\PausedMailRule;
use App\Infrastructure\Mail\Unsubscribe\SignedMailUnsubscribeLinks;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentMailPauseRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentMailPreferenceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentMailSubjectMuteRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
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
        $this->app->bind(MailPauseRepositoryInterface::class, EloquentMailPauseRepository::class);
        $this->app->bind(MailPauseServiceInterface::class, MailPauseService::class);
        // Things muted from a mail footer (core#334)
        $this->app->bind(MailSubjectMuteRepositoryInterface::class, EloquentMailSubjectMuteRepository::class);
        $this->app->bind(MailSubjectMuteServiceInterface::class, MailSubjectMuteService::class);
        $this->app->tag([CategoryPreferenceRule::class, PausedMailRule::class, MutedSubjectRule::class], MailDeliveryRuleInterface::TAG);
        $this->app->bind(MailPreferenceServiceInterface::class, fn (Application $app): MailPreferenceService => new MailPreferenceService(
            $app->make(MailCategoryRegistryInterface::class),
            $app->make(MailPreferenceRepositoryInterface::class),
            $app->tagged(MailDeliveryRuleInterface::TAG),
        ));

        $this->app->bind(MailCategoryOptOutStatisticsInterface::class, MailCategoryOptOutStatistics::class);

        $this->app->scoped(FilterMailByPreferences::class);

        // Footer and List-Unsubscribe headers of every mail to an account (core#330)
        $this->app->bind(MailUnsubscribeLinksInterface::class, SignedMailUnsubscribeLinks::class);
        $this->app->singleton(CurrentMailFooter::class);
        $this->app->bind(MailChannel::class, PreferenceAwareMailChannel::class);
    }

    public function boot(): void
    {
        Event::listen(NotificationSending::class, FilterMailByPreferences::class);

        // The HTML and text templates of the core both draw the footer from it
        View::composer('mail::message', static function (ViewContract $view): void {
            $view->with('mailFooter', app(CurrentMailFooter::class)->current());
        });
    }
}
