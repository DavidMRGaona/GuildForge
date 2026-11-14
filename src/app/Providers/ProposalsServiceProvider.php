<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Content\Services\EditorImageUploadAuthorizerInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Proposals\Contracts\ProposalApprovalStepInterface;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalQueryServiceInterface;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Application\Proposals\Services\ProposalTagOptionsInterface;
use App\Domain\Proposals\Events\ProposalApproved;
use App\Domain\Proposals\Events\ProposalRejected;
use App\Domain\Proposals\Events\ProposalReturned;
use App\Domain\Proposals\Events\ProposalSubmitted;
use App\Domain\Proposals\Events\ProposalWithdrawn;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Proposals\Listeners\NotifyProposalAuthor;
use App\Infrastructure\Proposals\Listeners\NotifyProposalReviewers;
use App\Infrastructure\Proposals\Listeners\ResolveProposalReviewNotices;
use App\Infrastructure\Proposals\Services\EventProposalRequestRegistry;
use App\Infrastructure\Proposals\Services\EventProposalRequestService;
use App\Infrastructure\Proposals\Services\ProposalEditorImageUploadAuthorizer;
use App\Infrastructure\Proposals\Services\ProposalEligibilityService;
use App\Infrastructure\Proposals\Services\ProposalQueryService;
use App\Infrastructure\Proposals\Services\ProposalService;
use App\Infrastructure\Proposals\Services\ProposalSettingsService;
use App\Infrastructure\Proposals\Services\ProposalTagOptions;
use App\Infrastructure\Proposals\Steps\ApplyEventProposalRequests;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Events and articles proposed from the web (core#570): settings, eligibility, the review flow and
 * what happens to proposals when their author's account goes, and the requests modules add to
 * event proposals (core#581).
 */
final class ProposalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProposalSettingsServiceInterface::class, ProposalSettingsService::class);
        $this->app->bind(ProposalEligibilityServiceInterface::class, ProposalEligibilityService::class);
        $this->app->bind(ProposalServiceInterface::class, ProposalService::class);
        $this->app->bind(ProposalQueryServiceInterface::class, ProposalQueryService::class);
        $this->app->bind(ProposalTagOptionsInterface::class, ProposalTagOptions::class);
        // Module requests of event proposals (core#581): modules register into the registry while they boot
        $this->app->singleton(EventProposalRequestRegistryInterface::class, EventProposalRequestRegistry::class);
        $this->app->bind(EventProposalRequestServiceInterface::class, EventProposalRequestService::class);
        $this->app->tag([ApplyEventProposalRequests::class], ProposalApprovalStepInterface::TAG);
        // Replaces the core's default (only whoever creates articles in the panel): adds whoever may propose them
        $this->app->singleton(EditorImageUploadAuthorizerInterface::class, ProposalEditorImageUploadAuthorizer::class);
    }

    public function boot(): void
    {
        // A deleted account takes its unpublished proposals with it, whatever deletes it; the
        // foreign keys would only empty proposed_by. Deactivating (soft deleting) keeps them.
        UserModel::forceDeleting(static function (UserModel $user): void {
            app(ProposalServiceInterface::class)->deleteUnpublishedOf((string) $user->getKey());
        });

        // Notices (mail and bell): reviewers when one is sent in, the author when it is resolved
        Event::listen(ProposalSubmitted::class, [NotifyProposalReviewers::class, 'handle']);
        Event::listen(ProposalApproved::class, [NotifyProposalAuthor::class, 'approved']);
        Event::listen(ProposalReturned::class, [NotifyProposalAuthor::class, 'returned']);
        Event::listen(ProposalRejected::class, [NotifyProposalAuthor::class, 'rejected']);

        // Resolved or withdrawn: the reviewers' notices asking for it are read (core#668)
        Event::listen(
            [ProposalApproved::class, ProposalReturned::class, ProposalRejected::class, ProposalWithdrawn::class],
            ResolveProposalReviewNotices::class,
        );

        // A deleted proposal (author, purge, account removal, the panel) leaves no mute and no
        // notice in the bell pointing at it
        EventModel::deleted($this->forgetNotices(...));
        ArticleModel::deleted($this->forgetNotices(...));
    }

    private function forgetNotices(Model $proposal): void
    {
        if ($proposal->getAttribute('proposal_status') === null && $proposal->getAttribute('proposed_by') === null) {
            return;
        }

        app(MailSubjectMuteServiceInterface::class)->forgetSubject('core.proposal', (string) $proposal->getKey());
    }
}
