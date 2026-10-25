<?php

declare(strict_types=1);

namespace App\Infrastructure\Seo;

use App\Application\DTOs\Response\ArticleResponseDTO;
use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\Response\GalleryDetailResponseDTO;
use App\Application\DTOs\Response\PhotoResponseDTO;
use App\Application\Seo\DTOs\SeoArticleDTO;
use App\Application\Seo\DTOs\SeoImage;
use App\Application\Seo\DTOs\SeoMetadata;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\DTOs\SeoScheduleDTO;
use App\Application\Seo\SeoType;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use Closure;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SeoMetadataFactory implements SeoMetadataFactoryInterface
{
    public const string LOCALE = 'es_ES';

    public const string DEFAULT_LOGO = '/images/defaults/logo-light.png';

    public function __construct(
        private SiteBrandingServiceInterface $branding,
        private CloudinaryImageUrlBuilder $images,
        private SeoDescriptionBuilder $descriptions,
        private StructuredDataBuilder $structuredData,
        private UrlGenerator $url,
    ) {}

    public function forSite(): SeoMetadata
    {
        return $this->safely(fn (): SeoMetadata => $this->buildSite(SeoMetadata::SCOPE_SITE, $this->url->current(), []));
    }

    public function forHome(): SeoMetadata
    {
        return $this->safely(function (): SeoMetadata {
            $site = $this->site();

            return $this->buildSite(SeoMetadata::SCOPE_PAGE, $site->url, [$this->structuredData->organization($site)]);
        });
    }

    public function forPage(SeoPageDTO $page): SeoMetadata
    {
        return $this->safely(fn (): SeoMetadata => $this->buildPage($page));
    }

    public function forEvent(EventResponseDTO $event): SeoMetadata
    {
        return $this->forPage(new SeoPageDTO(
            title: $event->title,
            description: $event->description,
            imagePublicId: $event->imagePublicId,
            schedule: new SeoScheduleDTO(
                start: $event->startDate,
                end: $event->endDate,
                location: $event->location,
                // No price at all means free, as the event page shows.
                price: $event->nonMemberPrice ?? $event->memberPrice ?? 0.0,
            ),
        ));
    }

    public function forArticle(ArticleResponseDTO $article): SeoMetadata
    {
        $publishedAt = $article->publishedAt ?? $article->createdAt;
        $excerpt = trim((string) $article->excerpt);

        return $this->forPage(new SeoPageDTO(
            title: $article->title,
            description: $excerpt !== '' ? $excerpt : $article->content,
            imagePublicId: $article->featuredImage,
            type: SeoType::Article,
            article: $publishedAt instanceof \DateTimeImmutable
                ? new SeoArticleDTO($publishedAt, $article->updatedAt, $article->author?->displayName)
                : null,
        ));
    }

    public function forGallery(GalleryDetailResponseDTO $gallery): SeoMetadata
    {
        $photos = $gallery->photos;
        usort($photos, fn (PhotoResponseDTO $a, PhotoResponseDTO $b): int => $a->sortOrder <=> $b->sortOrder);
        $description = __('seo.descriptions.gallery');

        return $this->forPage(new SeoPageDTO(
            title: $gallery->title,
            description: $gallery->description ?? (is_string($description) ? $description : null),
            imagePublicId: ($photos[0] ?? null)?->imagePublicId,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $structuredData
     */
    private function buildSite(string $scope, string $canonicalUrl, array $structuredData): SeoMetadata
    {
        $site = $this->site();

        return new SeoMetadata(
            scope: $scope,
            title: $site->name,
            fullTitle: $site->name,
            description: $this->descriptions->build(null, null, $site->description),
            canonicalUrl: $canonicalUrl,
            type: SeoType::Website,
            siteName: $site->name,
            locale: self::LOCALE,
            image: $this->siteImage($site->name),
            structuredData: $structuredData,
        );
    }

    private function buildPage(SeoPageDTO $page): SeoMetadata
    {
        $site = $this->site();
        $url = $this->url->current();
        $description = $this->descriptions->build($page->description, $page->schedule, $site->description);
        $alt = $page->imageAlt ?? $page->title;
        $image = $this->contentImage($page->imagePublicId, $alt) ?? $this->siteImage($alt);

        $structuredData = [];
        $extraMeta = [];

        if ($page->schedule instanceof SeoScheduleDTO) {
            $structuredData[] = $this->structuredData->event($page->title, $description, $url, $page->schedule, $image, $site);
        }

        if ($page->article instanceof SeoArticleDTO) {
            $structuredData[] = $this->structuredData->article($page->title, $description, $url, $page->article, $image, $site);
            $extraMeta[] = ['property' => 'article:published_time', 'content' => $this->structuredData->date($page->article->publishedAt)];

            if ($page->article->modifiedAt instanceof \DateTimeInterface) {
                $extraMeta[] = ['property' => 'article:modified_time', 'content' => $this->structuredData->date($page->article->modifiedAt)];
            }
        }

        return new SeoMetadata(
            scope: SeoMetadata::SCOPE_PAGE,
            title: $page->title,
            fullTitle: $page->title.' - '.$site->name,
            description: $description,
            canonicalUrl: $url,
            type: $page->type,
            siteName: $site->name,
            locale: self::LOCALE,
            image: $image,
            robots: $page->noindex ? 'noindex, nofollow' : null,
            extraMeta: $extraMeta,
            structuredData: $structuredData,
        );
    }

    private function site(): SeoSite
    {
        $name = $this->branding->siteName();

        return new SeoSite(
            name: $name,
            url: $this->url->to('/'),
            description: $this->descriptions->plainText((string) config('app.description', '')),
            logoUrl: $this->siteImage($name)->url,
            sameAs: $this->branding->socialProfileUrls(),
        );
    }

    private function contentImage(?string $publicId, string $alt): ?SeoImage
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        $url = $this->images->openGraph($publicId);

        return $url !== null
            ? new SeoImage($url, CloudinaryImageUrlBuilder::OG_WIDTH, CloudinaryImageUrlBuilder::OG_HEIGHT, $alt, true)
            : null;
    }

    /**
     * The site's own logo; GuildForge's default only when the site has none.
     */
    private function siteImage(string $alt): SeoImage
    {
        $logoPath = $this->branding->customLogoPath();

        if ($logoPath !== null) {
            $url = $this->images->logo($logoPath, $this->branding->customLogoIsDark());

            if ($url !== null) {
                return new SeoImage($url, CloudinaryImageUrlBuilder::LOGO_SIZE, CloudinaryImageUrlBuilder::LOGO_SIZE, $alt, false);
            }

            $storageUrl = $this->branding->customLogoUrl();

            if ($storageUrl !== null) {
                return new SeoImage($this->url->to($storageUrl), null, null, $alt, false);
            }
        }

        return new SeoImage($this->url->to(self::DEFAULT_LOGO), null, null, $alt, false);
    }

    /**
     * Metadata must never break a page: fall back to the site defaults, and
     * to the bare site name if even those fail.
     *
     * @param  Closure(): SeoMetadata  $build
     */
    private function safely(Closure $build): SeoMetadata
    {
        try {
            return $build();
        } catch (Throwable $e) {
            Log::warning('[SeoMetadataFactory] Falling back to site metadata', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            return $this->buildSite(SeoMetadata::SCOPE_SITE, $this->url->current(), []);
        } catch (Throwable) {
            // The settings may be what failed: the installation name needs none
            $name = (string) config('app.name', 'GuildForge');

            return new SeoMetadata(
                scope: SeoMetadata::SCOPE_SITE,
                title: $name,
                fullTitle: $name,
                description: '',
                canonicalUrl: $this->url->current(),
                type: SeoType::Website,
                siteName: $name,
                locale: self::LOCALE,
                image: null,
            );
        }
    }
}
