<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
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
    }
}
