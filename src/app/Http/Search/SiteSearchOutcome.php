<?php

declare(strict_types=1);

namespace App\Http\Search;

use App\Application\Search\DTOs\SiteSearchResultDTO;

/**
 * A search as run: its results, the tab actually searched (null for every type), the labels of the
 * types that could be searched and whether the search failed (and was answered with no results).
 */
final readonly class SiteSearchOutcome
{
    /**
     * @param  array<string, SiteSearchTypeLabels>  $labels  type => labels, in tab order
     */
    public function __construct(
        public SiteSearchResultDTO $result,
        public ?string $type,
        public array $labels,
        public bool $failed,
    ) {}

    /**
     * «Todo» first with every match, then each type with matches in tab order.
     *
     * @return list<array{key: string|null, label: string, count: int}>
     */
    public function tabs(string $allLabel): array
    {
        $tabs = [];
        foreach ($this->labels as $type => $labels) {
            $count = $this->result->counts[$type] ?? 0;
            if ($count > 0) {
                $tabs[] = ['key' => $type, 'label' => $labels->label, 'count' => $count];
            }
        }

        if ($tabs === []) {
            return [];
        }

        return [
            ['key' => null, 'label' => $allLabel, 'count' => array_sum(array_column($tabs, 'count'))],
            ...$tabs,
        ];
    }
}
