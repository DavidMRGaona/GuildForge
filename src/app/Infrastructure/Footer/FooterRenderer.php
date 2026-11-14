<?php

declare(strict_types=1);

namespace App\Infrastructure\Footer;

use App\Application\Footer\DTOs\FooterDTO;
use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Footer\Services\FooterRendererInterface;
use App\Application\Services\LegalPageServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves the stored footer for one person. Blocks go through the site block renderer, which leaves
 * out (and warns about) what cannot be drawn; a column left without blocks is dropped, so the others
 * take its room, and with no block left at all the site draws its fixed footer. Blocks are resolved on every request, never cached across requests: whether a
 * module's block can be drawn (a newsletter provider answering) and what a person may see change
 * between requests, and the stored layout already comes from the settings cache, forgotten on save.
 * Bound scoped: the shared prop asks once per request.
 */
final class FooterRenderer implements FooterRendererInterface
{
    /** @var array<string, FooterDTO|null> Per person, for this request */
    private array $resolved = [];

    public function __construct(
        private readonly FooterLayoutServiceInterface $layouts,
        private readonly SiteBlockRendererInterface $blocks,
        private readonly SiteBrandingServiceInterface $branding,
        private readonly LegalPageServiceInterface $legalPages,
    ) {}

    public function forUser(?Authenticatable $user): ?FooterDTO
    {
        $memo = $user instanceof Authenticatable ? 'user:'.$user->getAuthIdentifier() : 'guest';

        if (! array_key_exists($memo, $this->resolved)) {
            $this->resolved[$memo] = $this->resolve($user);
        }

        return $this->resolved[$memo];
    }

    private function resolve(?Authenticatable $user): ?FooterDTO
    {
        try {
            $layout = $this->layouts->layout();

            if ($layout->isEmpty()) {
                return null;
            }

            $columns = [];

            foreach ($layout->columns as $column) {
                $blocks = [];

                foreach ($column->blocks as $block) {
                    $resolved = $this->blocks->resolveBlock($block->key, $block->settings, SiteBlockPlacement::Footer, $user);

                    if ($resolved instanceof ResolvedSiteBlockDTO) {
                        $blocks[] = ['title' => $block->title, 'block' => $resolved];
                    }
                }

                if ($blocks !== []) {
                    $columns[] = $blocks;
                }
            }

            // Nothing left to draw (every block unavailable or unknown): the fixed footer, not an empty one
            if ($columns === []) {
                return null;
            }

            return new FooterDTO($columns, $this->branding->siteName(), $this->legalLinks());
        } catch (Throwable $e) {
            // Only the class: the fixed footer is drawn instead, the page is never broken by it
            Log::warning('[Footer] The configurable footer could not be built; the fixed footer is drawn instead.', [
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function legalLinks(): array
    {
        $links = [];

        foreach ($this->legalPages->getPublishedTitles() as $slug => $title) {
            $links[] = ['label' => $title, 'href' => route('legal.show', ['slug' => $slug], false)];
        }

        return $links;
    }
}
