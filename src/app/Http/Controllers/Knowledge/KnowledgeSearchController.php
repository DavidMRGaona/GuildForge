<?php

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeSearchRequest;
use Illuminate\Http\JsonResponse;

final class KnowledgeSearchController extends Controller
{
    public const int LIMIT = 8;

    public function __construct(private readonly KnowledgeQueryServiceInterface $knowledge) {}

    public function __invoke(KnowledgeSearchRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $request->isSearchable()) {
            return response()->json(['results' => []]);
        }

        $hits = $this->knowledge->search($user, GuideAudience::Site, $request->searchQuery(), self::LIMIT);

        return response()->json([
            'results' => array_map(
                static fn (KnowledgeSearchHitDTO $hit): array => [
                    'id' => $hit->id,
                    'title' => $hit->title,
                    'categoryId' => $hit->categoryId,
                    'snippet' => $hit->snippet,
                ],
                $hits,
            ),
        ]);
    }
}
