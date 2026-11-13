<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proposals;

use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\ProposalException;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Domain\Proposals\Exceptions\ProposalNotFoundException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

/**
 * What an author does to a proposal from «Mis propuestas»: send it to review, take it back from
 * review, delete it (core#579). The service decides who may and when; this only answers.
 */
final class ProposalActionController extends Controller
{
    /** What the URL says, per type of proposal */
    private const array TYPES = ['eventos' => ProposalType::Event, 'articulos' => ProposalType::Article];

    public function __construct(
        private readonly ProposalServiceInterface $proposals,
    ) {}

    public function submit(Request $request, string $type, string $id): RedirectResponse
    {
        return $this->act($request, $type, function (string $userId, ProposalType $proposalType) use ($id): string {
            $status = $this->proposals->submit($userId, $proposalType, $id);

            return $status === ProposalStatus::Approved ? 'proposals.flash.published' : 'proposals.flash.submitted';
        });
    }

    public function withdraw(Request $request, string $type, string $id): RedirectResponse
    {
        return $this->act($request, $type, function (string $userId, ProposalType $proposalType) use ($id): string {
            $this->proposals->withdraw($userId, $proposalType, $id);

            return 'proposals.flash.withdrawn';
        });
    }

    public function destroy(Request $request, string $type, string $id): RedirectResponse
    {
        return $this->act($request, $type, function (string $userId, ProposalType $proposalType) use ($id): string {
            $this->proposals->delete($userId, $proposalType, $id);

            return 'proposals.flash.deleted';
        });
    }

    /**
     * @param  callable(string, ProposalType): string  $action  does it and answers the key of the success message
     */
    private function act(Request $request, string $type, callable $action): RedirectResponse
    {
        $proposalType = self::TYPES[$type] ?? null;
        abort_if($proposalType === null, 404);

        try {
            $flash = $action((string) $request->user()?->getAuthIdentifier(), $proposalType);
        } catch (ProposalNotFoundException) {
            abort(404);
        } catch (ProposalNotAllowedException $exception) {
            // Someone else's proposal looks like one that does not exist
            abort($exception->reason === 'not_author' ? 404 : 403);
        } catch (ProposalException $exception) {
            $key = 'proposals.errors.'.$exception->reason;

            return redirect()->to('/perfil?tab=proposals')
                ->with('error', Lang::has($key) ? __($key) : __('proposals.errors.generic'));
        }

        return redirect()->to('/perfil?tab=proposals')->with('success', __($flash));
    }
}
