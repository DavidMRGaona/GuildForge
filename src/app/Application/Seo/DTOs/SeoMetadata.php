<?php

declare(strict_types=1);

namespace App\Application\Seo\DTOs;

use App\Application\Seo\SeoType;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Everything the <head> needs for one page, rendered by the server for
 * crawlers and applied by the client on SPA navigation.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SeoMetadata implements Arrayable
{
    /** Site-wide defaults shared with every page. */
    public const string SCOPE_SITE = 'site';

    /** Metadata built for one specific page. */
    public const string SCOPE_PAGE = 'page';

    /**
     * @param  list<array{property: string, content: string}>  $extraMeta
     * @param  list<array<string, mixed>>  $structuredData
     */
    public function __construct(
        public string $scope,
        public string $title,
        public string $fullTitle,
        public string $description,
        public string $canonicalUrl,
        public SeoType $type,
        public string $siteName,
        public string $locale,
        public ?SeoImage $image,
        public ?string $robots = null,
        public array $extraMeta = [],
        public array $structuredData = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'title' => $this->title,
            'fullTitle' => $this->fullTitle,
            'description' => $this->description,
            'canonicalUrl' => $this->canonicalUrl,
            'type' => $this->type->value,
            'siteName' => $this->siteName,
            'locale' => $this->locale,
            'image' => $this->image?->toArray(),
            'robots' => $this->robots,
            'extraMeta' => $this->extraMeta,
            'structuredData' => $this->structuredData,
        ];
    }
}
