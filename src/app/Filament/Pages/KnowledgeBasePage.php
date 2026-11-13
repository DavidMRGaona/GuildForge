<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Knowledge\DTOs\Response\KnowledgeCategoryPageResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeGuideResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeHomeResponseDTO;
use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Impersonation\ReadsOnly;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Throwable;

/**
 * The «Ayuda» page of the panel: documentation-style reader for the panel guides.
 */
final class KnowledgeBasePage extends Page
{
    private const int SEARCH_LIMIT = 10;

    private const int MIN_QUERY_LENGTH = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    /** Reached from the user menu and the top bar, not from the sidebar. */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'ayuda';

    protected string $view = 'filament.pages.knowledge-base';

    #[Url(as: 'guia')]
    public ?string $guide = null;

    #[Url(as: 'q')]
    public string $search = '';

    public static function getNavigationLabel(): string
    {
        return __('knowledge.panel.title');
    }

    public function getTitle(): string
    {
        return __('knowledge.panel.title');
    }

    public static function canAccess(): bool
    {
        // Signing in is enough here: the panel itself already requires admin.access
        return auth()->check();
    }

    #[ReadsOnly]
    public function openGuide(string $guideId): void
    {
        $this->guide = $guideId;
        $this->search = '';
        $this->scrollToContent();
    }

    #[ReadsOnly]
    public function showHome(): void
    {
        $this->guide = null;
        $this->search = '';
        $this->scrollToContent();
    }

    /**
     * The guide index needs the room: a sidebar that can collapse folds while this page is open.
     */
    public function foldsPanelSidebar(): bool
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return $panel !== null
            && ($panel->isSidebarCollapsibleOnDesktop() || $panel->isSidebarFullyCollapsibleOnDesktop());
    }

    /**
     * A guide opened from far down the page (a related guide, a search hit) starts at its top, not
     * where the previous one was being read. Runs once the new content is on the page; when its
     * start is already in view (below the sticky top bar), the page stays where it is.
     */
    private function scrollToContent(): void
    {
        $this->js(<<<'JS'
            const content = document.getElementById('kb-content')
            if (content && content.getBoundingClientRect().top < 96) content.scrollIntoView({ block: 'start' })
            JS);
    }

    /**
     * Counts the search as a miss only when it was submitted and found nothing.
     */
    #[ReadsOnly]
    public function submitSearch(): void
    {
        $query = trim($this->search);
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return;
        }

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        if ($this->queries()->search($user, GuideAudience::Panel, $query, self::SEARCH_LIMIT) === []) {
            // Counting a miss is a side note: a broken metrics store must not break the search
            try {
                app(KnowledgeMetricsServiceInterface::class)->recordMiss($query, GuideAudience::Panel);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->emptyViewData();
        }

        $queries = $this->queries();
        $home = $queries->home($user, GuideAudience::Panel);

        $query = trim($this->search);
        $hits = mb_strlen($query) >= self::MIN_QUERY_LENGTH
            ? $queries->search($user, GuideAudience::Panel, $query, self::SEARCH_LIMIT)
            : null;

        $guide = null;
        $unavailable = false;
        if ($this->guide !== null && $this->guide !== '') {
            $guide = $this->resolveGuide($user);
            $unavailable = ! $guide instanceof KnowledgeGuideResponseDTO;
        }

        return [
            'home' => $home,
            'categoryPages' => $this->categoryPages($user, $home),
            'hits' => $hits,
            'selectedGuide' => $guide,
            'unavailable' => $unavailable,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyViewData(): array
    {
        return [
            'home' => new KnowledgeHomeResponseDTO([], []),
            'categoryPages' => [],
            'hits' => null,
            'selectedGuide' => null,
            'unavailable' => $this->guide !== null && $this->guide !== '',
        ];
    }

    private function resolveGuide(object $user): ?KnowledgeGuideResponseDTO
    {
        try {
            $id = GuideId::fromString((string) $this->guide);
        } catch (InvalidGuideIdException) {
            return null;
        }

        $queries = $this->queries();

        return $queries->guide($user, GuideAudience::Panel, $queries->resolveAlias($id) ?? $id);
    }

    /**
     * @return list<KnowledgeCategoryPageResponseDTO>
     */
    private function categoryPages(object $user, KnowledgeHomeResponseDTO $home): array
    {
        $pages = [];
        foreach ($home->categories as $category) {
            $page = $this->queries()->category($user, GuideAudience::Panel, $category->id);
            if ($page instanceof KnowledgeCategoryPageResponseDTO) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return app(KnowledgeQueryServiceInterface::class);
    }
}
