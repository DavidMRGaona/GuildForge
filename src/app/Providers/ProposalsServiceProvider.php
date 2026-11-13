<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Proposals\Services\ProposalEligibilityService;
use App\Infrastructure\Proposals\Services\ProposalService;
use App\Infrastructure\Proposals\Services\ProposalSettingsService;
use Illuminate\Support\ServiceProvider;

/**
 * Events and articles proposed from the web (core#570): settings, eligibility, the review flow and
 * what happens to proposals when their author's account goes.
 */
final class ProposalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProposalSettingsServiceInterface::class, ProposalSettingsService::class);
        $this->app->bind(ProposalEligibilityServiceInterface::class, ProposalEligibilityService::class);
        $this->app->bind(ProposalServiceInterface::class, ProposalService::class);
    }

    public function boot(): void
    {
        // A deleted account takes its unpublished proposals with it, whatever deletes it; the
        // foreign keys would only empty proposed_by. Deactivating (soft deleting) keeps them.
        UserModel::forceDeleting(static function (UserModel $user): void {
            app(ProposalServiceInterface::class)->deleteUnpublishedOf((string) $user->getKey());
        });
    }
}
