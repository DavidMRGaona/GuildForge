<?php

declare(strict_types=1);

namespace App\Application\Seo\Services;

use App\Application\DTOs\Response\ArticleResponseDTO;
use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\Response\GalleryDetailResponseDTO;
use App\Application\Seo\DTOs\SeoMetadata;
use App\Application\Seo\DTOs\SeoPageDTO;

/**
 * Builds the metadata rendered in the <head> of public pages.
 *
 * Modules use forPage() and send the result as the `seo` Inertia prop.
 * Implementations never throw: on failure they fall back to site defaults.
 */
interface SeoMetadataFactoryInterface
{
    public function forSite(): SeoMetadata;

    public function forHome(): SeoMetadata;

    public function forPage(SeoPageDTO $page): SeoMetadata;

    public function forEvent(EventResponseDTO $event): SeoMetadata;

    public function forArticle(ArticleResponseDTO $article): SeoMetadata;

    public function forGallery(GalleryDetailResponseDTO $gallery): SeoMetadata;
}
