<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\DTOs\KnowledgeIndexResultDTO;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Contracts\UpdatesIndexSettings;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use RuntimeException;
use Throwable;

/**
 * Pushes the index settings, then the active guides, and removes the inactive ones from the index.
 *
 * The settings go through the engine (what `scout:sync-index-settings` does for this index) rather than the
 * command, because the command reports a failure and still exits with 0.
 */
final readonly class ScoutKnowledgeIndexer implements KnowledgeIndexerInterface
{
    private const int CHUNK = 500;

    public function __construct(
        private EngineManager $engines,
        private Repository $config,
    ) {}

    public function reindex(): KnowledgeIndexResultDTO
    {
        try {
            $engine = $this->engines->engine();
            $this->syncIndexSettings($engine);

            // Straight to the engine (not through the queue), so the result reflects what the index received
            $indexed = 0;
            KnowledgeGuideModel::query()->where('active', true)->chunkById(self::CHUNK, function (Collection $guides) use ($engine, &$indexed): void {
                $engine->update($guides);
                $indexed += $guides->count();
            });

            $removed = 0;
            KnowledgeGuideModel::query()->where('active', false)->chunkById(self::CHUNK, function (Collection $guides) use ($engine, &$removed): void {
                $engine->delete($guides);
                $removed += $guides->count();
            });

            return new KnowledgeIndexResultDTO($indexed, $removed, null);
        } catch (Throwable $e) {
            return new KnowledgeIndexResultDTO(0, 0, $e->getMessage());
        }
    }

    private function syncIndexSettings(Engine $engine): void
    {
        if (! $engine instanceof UpdatesIndexSettings) {
            throw new RuntimeException('The configured Scout engine cannot update index settings.');
        }

        $driver = $this->config->get('scout.driver');
        $settings = $this->config->get('scout.'.(is_string($driver) ? $driver : '').'.index-settings.'.KnowledgeGuideModel::class);

        $engine->updateIndexSettings((new KnowledgeGuideModel)->searchableAs(), is_array($settings) ? $settings : []);
    }
}
