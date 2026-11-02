<?php

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeFeedbackRequest;
use Illuminate\Http\Response;
use Throwable;

final class KnowledgeFeedbackController extends Controller
{
    public function __construct(
        private readonly GuideVisibilityServiceInterface $visibility,
        private readonly KnowledgeMetricsServiceInterface $metrics,
    ) {}

    public function __invoke(KnowledgeFeedbackRequest $request, string $guide): Response
    {
        try {
            $id = GuideId::fromString($guide);
        } catch (InvalidGuideIdException) {
            abort(404);
        }

        $user = $request->user();
        abort_if($user === null, 401);
        abort_if(! $this->visibility->findVisible($user, $id, GuideAudience::Site) instanceof Guide, 404);

        // One vote per guide and session: a repeat is acknowledged but not counted
        $key = 'knowledge.voted.'.$id->value();
        if (! $request->session()->has($key)) {
            // The vote is acknowledged even when the metrics store fails: the reader has nothing to fix
            try {
                $this->metrics->recordVote($id, $request->isHelpful());
                $request->session()->put($key, true);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->noContent();
    }
}
