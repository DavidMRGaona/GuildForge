<?php

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Application\Knowledge\DTOs\Response\KnowledgeCategoryPageResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeGuideResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
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
        private readonly KnowledgeMetricsServiceInterface $metrics,
    ) {}

    public function index(KnowledgeSearchRequest $request): Response
    {
        $user = $this->user($request);
        $query = $request->searchQuery();

        $results = null;
        if ($query !== '') {
            $results = $request->isSearchable()
                ? array_map(
                    static fn (KnowledgeSearchHitDTO $hit): array => $hit->toArray(),
                    $this->knowledge->search($user, GuideAudience::Site, $query, self::SEARCH_LIMIT),
                )
                : [];

            if ($request->isSearchable() && $results === []) {
                $this->metrics->recordMiss($query, GuideAudience::Site);
            }
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
            abort_if(! $current instanceof GuideId || $current->equals($id), 404);

            // Redirect only to a guide this user may see: a hidden target must look like a missing id
            abort_if(! $this->knowledge->guide($user, GuideAudience::Site, $current) instanceof KnowledgeGuideResponseDTO, 404);

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
