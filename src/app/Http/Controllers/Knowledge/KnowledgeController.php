<?php

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Application\Knowledge\DTOs\Response\KnowledgeCategoryPageResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeGuideResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeSearchRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KnowledgeController extends Controller
{
    private const int SEARCH_LIMIT = 10;

    public function __construct(
        private readonly KnowledgeQueryServiceInterface $knowledge,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function index(KnowledgeSearchRequest $request): Response
    {
        $user = $this->user($request);
        $query = $request->searchQuery();

        $results = null;
        if ($query !== '') {
            $results = $request->isSearchable()
                ? array_map(
                    static fn (KnowledgeSearchHitDTO $hit): array => [
                        'id' => $hit->id,
                        'title' => $hit->title,
                        'categoryId' => $hit->categoryId,
                        'snippet' => $hit->snippet,
                    ],
                    $this->knowledge->search($user, GuideAudience::Site, $query, self::SEARCH_LIMIT),
                )
                : [];
        }

        return Inertia::render('Knowledge/Index', [
            'home' => $this->knowledge->home($user, GuideAudience::Site)->toArray(),
            'query' => $query,
            'results' => $results,
            'seo' => $this->seoFor((string) __('seo.titles.knowledge')),
        ]);
    }

    public function category(Request $request, string $category): Response
    {
        $page = $this->knowledge->category($this->user($request), GuideAudience::Site, $category);
        abort_if(! $page instanceof KnowledgeCategoryPageResponseDTO, 404);

        return Inertia::render('Knowledge/Category', [
            'page' => $page->toArray(),
            'seo' => $this->seoFor($page->category->label),
        ]);
    }

    public function show(Request $request, string $guide): Response|RedirectResponse
    {
        try {
            $id = GuideId::fromString($guide);
        } catch (InvalidGuideIdException) {
            abort(404);
        }

        $user = $this->user($request);
        $response = $this->knowledge->guide($user, GuideAudience::Site, $id);

        if (! $response instanceof KnowledgeGuideResponseDTO) {
            $current = $this->knowledge->resolveAlias($id);
            abort_if(! $current instanceof GuideId, 404);

            // The new id is checked for visibility when it is requested
            return redirect()->route('knowledge.show', ['guide' => $current->value()], 301);
        }

        return Inertia::render('Knowledge/Show', [
            'guide' => $response->toArray(),
            'seo' => $this->seoFor($response->title),
        ]);
    }

    private function user(Request $request): object
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function seoFor(string $title): array
    {
        return $this->seo->forPage(new SeoPageDTO(title: $title, noindex: true))->toArray();
    }
}
