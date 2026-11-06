<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\DTOs\KnowledgeIndexResultDTO;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Domain\Knowledge\ValueObjects\GuideId;
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

    public function refresh(GuideId $id): void
    {
        try {
            $guides = KnowledgeGuideModel::query()->where('guide_id', $id->value())->get();
            $engine = $this->engines->engine();

            $active = $guides->filter(static fn (KnowledgeGuideModel $guide): bool => $guide->active);
            $inactive = $guides->reject(static fn (KnowledgeGuideModel $guide): bool => $guide->active);
            if ($active->isNotEmpty()) {
                $engine->update($active);
            }
            if ($inactive->isNotEmpty()) {
                $engine->delete($inactive);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function forget(GuideId $id): void
    {
        try {
            $guides = KnowledgeGuideModel::query()->where('guide_id', $id->value())->get();
            if ($guides->isNotEmpty()) {
                $this->engines->engine()->delete($guides);
            }
        } catch (Throwable $e) {
            report($e);
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
