<?php

declare(strict_types=1);

namespace App\Http\Search;

use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Search\Services\SearchAudienceResolverInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Application\Search\Services\SiteSearchInterface;
use App\Infrastructure\Search\Search\HelpHitsProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs one site search for the results page or the header panel: the types of the enabled sources,
 * the person's audiences, the help when they may have it. Never throws: whatever fails is logged
 * and answered with no results.
 */
final readonly class SiteSearchRunner
{
    public function __construct(
        private SiteSearchInterface $search,
        private SearchAudienceResolverInterface $audiences,
        private SearchSourceRegistryInterface $sources,
        private HelpHitsProvider $help,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param  string|null  $tab  the tab asked for; one that does not exist (or the help, for someone who may not have it) searches every type
     */
    public function run(string $query, ?object $user, ?string $tab, int $offset, int $limit): SiteSearchOutcome
    {
        try {
            $labels = $this->labels($this->help->availableFor($user));
            $type = $tab !== null && isset($labels[$tab]) ? $tab : null;
            $types = array_values(array_filter(array_keys($labels), static fn (string $key): bool => $key !== HelpHitsProvider::TYPE));

            $result = $this->search->search(new SiteSearchQueryDTO(
                query: $query,
                audiences: $this->audiences->for($user),
                types: $types,
                type: $type,
                offset: $offset,
                limit: $limit,
                user: $user,
                includeHelp: isset($labels[HelpHitsProvider::TYPE]),
            ));

            return new SiteSearchOutcome($result, $type, $labels, failed: false);
        } catch (Throwable $exception) {
            $this->logger->warning('[SiteSearch] Search failed, answering with no results: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new SiteSearchOutcome(SiteSearchResultDTO::empty(), null, [], failed: true);
        }
    }

    /**
     * Labels of every searchable type in registration order, the help last; a source whose labels
     * cannot be read is logged and left out.
     *
     * @return array<string, SiteSearchTypeLabels>
     */
    private function labels(bool $includeHelp): array
    {
        $labels = [];
        foreach ($this->sources->indexedSources() as $source) {
            try {
                $labels[$source->type()] = new SiteSearchTypeLabels($source->label(), $source->itemLabel(), $source->pastLabel());
            } catch (Throwable $exception) {
                $this->logger->warning('[SiteSearch] Search source left out, its labels could not be read: '.$exception->getMessage(), [
                    'source' => $source::class,
                    'exception' => $exception,
                ]);
            }
        }

        // The help is a type of its own, whatever a source calls itself
        unset($labels[HelpHitsProvider::TYPE]);
        if ($includeHelp) {
            $help = (string) __('search.help');
            $labels[HelpHitsProvider::TYPE] = new SiteSearchTypeLabels($help, $help, null);
        }

        return $labels;
    }
}
