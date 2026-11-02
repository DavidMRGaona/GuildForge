<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Search\DTOs\SiteSearchHitDTO;
use App\Application\Services\TimezoneServiceInterface;
use App\Http\Search\SiteSearchTypeLabels;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the site search, the same shape on the results page and in the header panel.
 *
 * @property SiteSearchHitDTO $resource
 */
final class SiteSearchHitResource extends JsonResource
{
    /**
     * @param  array<string, SiteSearchTypeLabels>  $labels  type => labels
     */
    public function __construct(SiteSearchHitDTO $hit, private readonly array $labels)
    {
        parent::__construct($hit);
    }

    /**
     * @param  list<SiteSearchHitDTO>  $hits
     * @param  array<string, SiteSearchTypeLabels>  $labels
     * @return list<array<string, mixed>>
     */
    public static function rows(array $hits, array $labels): array
    {
        return array_map(static fn (SiteSearchHitDTO $hit): array => (new self($hit, $labels))->resolve(), $hits);
    }

    /**
     * @return array{type: string, typeLabel: string, title: string, url: string, meta: ?string, image: ?string, date: ?string, pastLabel: ?string, snippet: list<array{text: string, match: bool}>}
     */
    public function toArray(Request $request): array
    {
        $hit = $this->resource;
        $labels = $this->labels[$hit->type] ?? null;

        return [
            'type' => $hit->type,
            'typeLabel' => $labels !== null ? $labels->itemLabel : $hit->type,
            'title' => $hit->title,
            'url' => $hit->url,
            'meta' => $hit->meta,
            'image' => $hit->imagePublicId,
            'date' => $this->date($hit),
            'pastLabel' => $hit->past ? $labels?->pastLabel : null,
            'snippet' => $hit->snippet,
        ];
    }

    /**
     * The start with its time («sáb 15 nov · 10:00»), else the publication day («15 nov 2026»), in the display zone.
     */
    private function date(SiteSearchHitDTO $hit): ?string
    {
        return match (true) {
            $hit->startsAt instanceof DateTimeImmutable => $this->format($hit->startsAt, 'ddd D MMM · HH:mm'),
            $hit->publishedAt instanceof DateTimeImmutable => $this->format($hit->publishedAt, 'D MMM YYYY'),
            default => null,
        };
    }

    private function format(DateTimeInterface $instant, string $format): string
    {
        $display = CarbonImmutable::instance(app(TimezoneServiceInterface::class)->toDisplay($instant))
            ->settings(['locale' => app()->getLocale()]);

        // Spanish short names end in a dot («sáb.», «nov.»); the row reads better without them
        return str_replace('.', '', $display->isoFormat($format));
    }
}
