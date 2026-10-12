<?php

declare(strict_types=1);

namespace App\Infrastructure\Seo;

use App\Application\Seo\DTOs\SeoArticleDTO;
use App\Application\Seo\DTOs\SeoImage;
use App\Application\Seo\DTOs\SeoScheduleDTO;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * schema.org JSON-LD blocks. Optional properties are omitted rather than
 * sent empty, which validators flag as errors.
 */
final readonly class StructuredDataBuilder
{
    private const string CONTEXT = 'https://schema.org';

    private const int HEADLINE_MAX_LENGTH = 110;

    public function __construct(
        private string $timezone,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function organization(SeoSite $site): array
    {
        $data = [
            '@context' => self::CONTEXT,
            '@type' => 'Organization',
            'name' => $site->name,
            'url' => $site->url,
        ];

        if ($site->description !== '') {
            $data['description'] = $site->description;
        }

        if ($site->logoUrl !== null) {
            $data['logo'] = $site->logoUrl;
        }

        if ($site->sameAs !== []) {
            $data['sameAs'] = $site->sameAs;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function event(
        string $name,
        string $description,
        string $url,
        SeoScheduleDTO $schedule,
        ?SeoImage $image,
        SeoSite $site,
    ): array {
        $data = [
            '@context' => self::CONTEXT,
            '@type' => 'Event',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'startDate' => $this->date($schedule->start),
            'endDate' => $this->date($schedule->end ?? $schedule->start),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'organizer' => ['@type' => 'Organization', 'name' => $site->name, 'url' => $site->url],
        ];

        if ($image !== null) {
            $data['image'] = [$image->url];
        }

        $location = trim((string) $schedule->location);
        if ($location !== '') {
            $data['location'] = ['@type' => 'Place', 'name' => $location, 'address' => $location];
        }

        if ($schedule->price !== null) {
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => number_format($schedule->price, 2, '.', ''),
                'priceCurrency' => 'EUR',
                'url' => $url,
                'availability' => 'https://schema.org/InStock',
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function article(
        string $headline,
        string $description,
        string $url,
        SeoArticleDTO $article,
        ?SeoImage $image,
        SeoSite $site,
    ): array {
        $publisher = ['@type' => 'Organization', 'name' => $site->name, 'url' => $site->url];

        if ($site->logoUrl !== null) {
            $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $site->logoUrl];
        }

        $data = [
            '@context' => self::CONTEXT,
            '@type' => 'Article',
            'headline' => mb_substr($headline, 0, self::HEADLINE_MAX_LENGTH),
            'description' => $description,
            'url' => $url,
            'mainEntityOfPage' => $url,
            'datePublished' => $this->date($article->publishedAt),
            'dateModified' => $this->date($article->modifiedAt ?? $article->publishedAt),
            'publisher' => $publisher,
        ];

        if ($article->authorName !== null && $article->authorName !== '') {
            $data['author'] = ['@type' => 'Person', 'name' => $article->authorName];
        }

        if ($image !== null) {
            $data['image'] = [$image->url];
        }

        return $data;
    }

    public function date(DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->setTimezone($this->timezone)->toAtomString();
    }
}
