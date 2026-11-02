<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Index;

use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Illuminate\Contracts\Config\Repository;
use Laravel\Scout\Contracts\UpdatesIndexSettings;
use Laravel\Scout\EngineManager;
use RuntimeException;

/**
 * Talks to the engine directly (not through Scout's model events, which are off for search documents).
 *
 * The settings go through the engine (what `scout:sync-index-settings` does for this index) rather than the
 * command, because the command reports a failure and still exits with 0.
 */
final readonly class ScoutSearchIndexWriter implements SearchIndexWriterInterface
{
    private const int CHUNK = 500;

    public function __construct(
        private EngineManager $engines,
        private Repository $config,
    ) {}

    public function syncSettings(): void
    {
        $engine = $this->engines->engine();

        if (! $engine instanceof UpdatesIndexSettings) {
            throw new RuntimeException('The configured Scout engine cannot update index settings.');
        }

        $driver = $this->config->get('scout.driver');
        $settings = $this->config->get('scout.'.(is_string($driver) ? $driver : '').'.index-settings.'.SearchDocumentModel::class);

        $engine->updateIndexSettings((new SearchDocumentModel)->searchableAs(), is_array($settings) ? $settings : []);
    }

    public function put(array $rowIds): void
    {
        foreach (array_chunk(array_values(array_unique($rowIds)), self::CHUNK) as $chunk) {
            $models = SearchDocumentModel::query()->whereIn('id', $chunk)->get();

            if ($models->isNotEmpty()) {
                $this->engines->engine()->update($models);
            }
        }
    }

    public function remove(array $rowIds): void
    {
        foreach (array_chunk(array_values(array_unique($rowIds)), self::CHUNK) as $chunk) {
            // The rows are gone: the engine only needs their keys
            $models = (new SearchDocumentModel)->newCollection(array_map(
                static fn (string $id): SearchDocumentModel => (new SearchDocumentModel)->forceFill(['id' => $id]),
                $chunk,
            ));

            $this->engines->engine()->delete($models);
        }
    }
}
