<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Renders the SEO tags of the first page load, for crawlers that do not run
 * JavaScript. The SPA removes them on boot (data-seo-server) and manages the
 * head itself from the same `seo` prop.
 */
final class SeoHead extends Component
{
    private const int JSON_LD_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * @param  array<string, mixed>|null  $seo  The page's `seo` prop
     */
    public function __construct(
        public ?array $seo = null,
    ) {}

    public function render(): View
    {
        $seo = $this->seo ?? app(SeoMetadataFactoryInterface::class)->forSite()->toArray();

        /** @var list<array<string, mixed>> $blocks */
        $blocks = $seo['structuredData'] ?? [];

        return view('components.seo-head', [
            'meta' => $seo,
            // JSON_HEX_TAG turns "<" and ">" into \u003C and \u003E, so text containing
            // "</script>" cannot close the tag.
            'jsonLd' => array_map(
                fn (array $block): string => json_encode($block, self::JSON_LD_FLAGS),
                $blocks,
            ),
        ]);
    }
}
