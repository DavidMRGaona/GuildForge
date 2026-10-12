<?php

declare(strict_types=1);

namespace App\Infrastructure\Seo;

/**
 * Identity of the site as shown to crawlers.
 */
final readonly class SeoSite
{
    /**
     * @param  list<string>  $sameAs  Social profile URLs
     */
    public function __construct(
        public string $name,
        public string $url,
        public string $description,
        public ?string $logoUrl,
        public array $sameAs,
    ) {}
}
