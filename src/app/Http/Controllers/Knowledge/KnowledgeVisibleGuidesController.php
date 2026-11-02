<?php

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells the site which public guides the signed-in user may open, so contextual help
 * links can hide themselves instead of leading to a 404.
 */
final class KnowledgeVisibleGuidesController extends Controller
{
    public function __construct(private readonly KnowledgeQueryServiceInterface $knowledge) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json([
            'ids' => $this->knowledge->visibleIds($user, GuideAudience::Site),
        ]);
    }
}
