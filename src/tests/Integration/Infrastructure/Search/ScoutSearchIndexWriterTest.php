<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Infrastructure\Search\Index\ScoutSearchIndexWriter;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Scout\Contracts\UpdatesIndexSettings;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class ScoutSearchIndexWriterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function writerWith(Engine $engine): ScoutSearchIndexWriter
    {
        $engines = Mockery::mock(EngineManager::class);
        $engines->shouldReceive('engine')->andReturn($engine);

        return new ScoutSearchIndexWriter($engines, $this->app->make('config'));
    }

    private function row(string $id): string
    {
        return $this->app->make(SearchDocumentStoreInterface::class)
            ->upsert('event', null, new SearchDocumentDTO(id: $id, title: 'T', body: 'B', url: '/e/'.$id));
    }

    public function test_put_sends_the_stored_rows_to_the_engine(): void
    {
        $a = $this->row('a');
        $b = $this->row('b');
        /** @var Engine&MockInterface $engine */
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('update')->once()->with(Mockery::on(
            fn (Collection $models): bool => $models->every(fn (mixed $model): bool => $model instanceof SearchDocumentModel)
                && $models->pluck('id')->sort()->values()->all() === collect([$a, $b])->sort()->values()->all(),
        ));

        $this->writerWith($engine)->put([$a, $b, 'missing-row']);
    }

    public function test_remove_sends_the_keys_of_deleted_rows(): void
    {
        /** @var Engine&MockInterface $engine */
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('delete')->once()->with(Mockery::on(
            fn (Collection $models): bool => $models->map(fn (Model $model): mixed => $model->getKey())->all() === ['x', 'y'],
        ));

        $this->writerWith($engine)->remove(['x', 'y']);
    }

    public function test_nothing_is_sent_for_an_empty_list(): void
    {
        /** @var Engine&MockInterface $engine */
        $engine = Mockery::mock(Engine::class);
        $engine->shouldNotReceive('update');
        $engine->shouldNotReceive('delete');

        $writer = $this->writerWith($engine);
        $writer->put([]);
        $writer->remove([]);
    }

    public function test_sync_settings_pushes_the_configured_index_settings(): void
    {
        config(['scout.driver' => 'meilisearch', 'scout.meilisearch.index-settings.'.SearchDocumentModel::class => ['filterableAttributes' => ['type']]]);
        /** @var Engine&UpdatesIndexSettings&MockInterface $engine */
        $engine = Mockery::mock(Engine::class.', '.UpdatesIndexSettings::class);
        $engine->shouldReceive('updateIndexSettings')->once()->with('search_documents', ['filterableAttributes' => ['type']]);

        $this->writerWith($engine)->syncSettings();
    }

    public function test_sync_settings_fails_on_an_engine_without_index_settings(): void
    {
        $this->expectException(RuntimeException::class);

        $this->writerWith(Mockery::mock(Engine::class))->syncSettings();
    }
}
