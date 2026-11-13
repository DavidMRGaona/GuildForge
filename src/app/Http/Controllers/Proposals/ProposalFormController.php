<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proposals;

use App\Application\Proposals\DTOs\OwnProposalDTO;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalQueryServiceInterface;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Proposals\Enums\ProposalAuthorAction;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\ProposalException;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Domain\Proposals\Exceptions\ProposalNotFoundException;
use App\Filament\Forms\RichEditor\EditorImages;
use App\Http\Controllers\Controller;
use App\Http\Resources\OwnProposalResource;
use App\Http\Resources\TagResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the two proposal forms (event and article) share: the page, the answer to a refusal of the
 * service, sending to review after saving and where an uploaded image is stored (core#579).
 */
abstract class ProposalFormController extends Controller
{
    public function __construct(
        protected readonly ProposalServiceInterface $proposals,
        protected readonly ProposalQueryServiceInterface $queries,
        protected readonly ProposalEligibilityServiceInterface $eligibility,
        protected readonly TagQueryServiceInterface $tags,
        protected readonly TimezoneServiceInterface $timezone,
        protected readonly SeoMetadataFactoryInterface $seo,
    ) {}

    abstract protected function type(): ProposalType;

    /** Inertia page of the form */
    abstract protected function page(): string;

    /** Route name of the edit form, to come back to a draft that could not be sent */
    abstract protected function editRoute(): string;

    protected function formPage(Request $request, ?OwnProposalDTO $proposal): Response
    {
        $type = $this->type();

        return Inertia::render($this->page(), [
            'proposal' => $proposal instanceof OwnProposalDTO ? OwnProposalResource::make($proposal)->resolve() : null,
            'tags' => TagResource::collection($this->tags->getByType($type->value))->resolve(),
            ...$this->extraProps(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('proposals.seo.'.$type->value),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * What a type's form needs besides the common props.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [];
    }

    /** 403 for whoever may not propose this type */
    protected function assertMayPropose(Request $request): void
    {
        abort_unless($this->eligibility->check($this->type(), $this->userId($request))->allowed, 403);
    }

    /**
     * The proposal to edit: its author's (404 otherwise) and still a draft (otherwise back to the list).
     */
    protected function editable(Request $request, string $id): OwnProposalDTO|RedirectResponse
    {
        $proposal = $this->queries->findOwned($this->userId($request), $this->type(), $id);
        abort_if(! $proposal instanceof OwnProposalDTO, 404);

        if (! ProposalStatus::allowsAuthor($proposal->status, ProposalAuthorAction::Edit)) {
            return $this->toMyProposals()->with('error', __('proposals.errors.invalid_status'));
        }

        return $proposal;
    }

    /**
     * Where a person goes after saving: the list, having sent it to review when they asked to.
     * A proposal that cannot be sent stays a draft, back in its form with the reason.
     */
    protected function afterSaving(Request $request, string $id, bool $submit): RedirectResponse
    {
        if (! $submit) {
            return $this->toMyProposals()->with('success', __('proposals.flash.saved'));
        }

        try {
            $status = $this->proposals->submit($this->userId($request), $this->type(), $id);
        } catch (ProposalException $exception) {
            return redirect()->route($this->editRoute(), $id)
                ->with('error', __('proposals.flash.saved_not_sent', ['reason' => $this->message($exception)]));
        }

        return $this->toMyProposals()->with('success', $status === ProposalStatus::Approved
            ? __('proposals.flash.published')
            : __('proposals.flash.submitted'));
    }

    /**
     * The answer to a refusal of the service: 404 for what is not theirs or does not exist, 403
     * for who may not, and a message on the form for the rest (limits, a status that no longer
     * allows it).
     */
    protected function refusal(ProposalException $exception): RedirectResponse
    {
        if ($exception instanceof ProposalNotFoundException
            || ($exception instanceof ProposalNotAllowedException && $exception->reason === 'not_author')) {
            abort(404);
        }

        if ($exception instanceof ProposalNotAllowedException) {
            abort(403);
        }

        return back()->with('error', $this->message($exception));
    }

    /**
     * Stores an uploaded image in the folder the panel uses for the type ("events/<Y>/<m>" or
     * "articles/<Y>/<m>"), the one its media source claims. Returns the stored path.
     */
    protected function storeImage(mixed $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $path = Storage::disk(EditorImages::DISK)->putFileAs(
            $this->type()->value.'/'.now()->format('Y/m'),
            $file,
            Str::uuid()->toString().'.'.($file->guessExtension() ?? 'jpg'),
            ['visibility' => 'public', 'mimetype' => $file->getMimeType()],
        );

        abort_if(! is_string($path), 500);

        return $path;
    }

    protected function toMyProposals(): RedirectResponse
    {
        return redirect()->to('/perfil?tab=proposals');
    }

    protected function userId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }

    protected function message(ProposalException $exception): string
    {
        $key = 'proposals.errors.'.$exception->reason;

        return Lang::has($key) ? (string) __($key) : (string) __('proposals.errors.generic');
    }
}
