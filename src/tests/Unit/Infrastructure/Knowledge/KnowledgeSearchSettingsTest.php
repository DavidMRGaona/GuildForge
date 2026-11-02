<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Knowledge;

use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Search\KnowledgeSynonyms;
use Tests\TestCase;

final class KnowledgeSearchSettingsTest extends TestCase
{
    public function test_synonym_groups_expand_both_ways(): void
    {
        $this->assertSame(
            [
                'partida' => ['mesa'],
                'mesa' => ['partida', 'tablero'],
                'tablero' => ['mesa'],
            ],
            KnowledgeSynonyms::toIndexSettings([['partida', 'mesa'], ['mesa', 'tablero'], ['solo'], 'not a group', [42, '']]),
        );
    }

    public function test_the_index_settings_come_from_the_knowledge_config(): void
    {
        $settings = config('scout.meilisearch.index-settings')[KnowledgeGuideModel::class];

        $this->assertSame(['guide_id', 'locale', 'audience', 'active'], $settings['filterableAttributes']);
        $this->assertSame(['title', 'keywords', 'summary', 'body_text'], $settings['searchableAttributes']);
        $this->assertSame(['mesa'], $settings['synonyms']['partida']);
        $this->assertSame(['cancelar', 'desapuntarse'], $settings['synonyms']['darse de baja']);
        $this->assertSame(['apuntarse', 'apuntarme', 'inscribirse'], $settings['synonyms']['inscribirme']);
        $this->assertContains('como', $settings['stopWords']);
        $this->assertSame(config('knowledge.stop_words'), $settings['stopWords']);
    }

    public function test_the_searchable_document_has_the_indexed_fields(): void
    {
        $model = new KnowledgeGuideModel([
            'id' => 'b6d7c7f4-1d1a-4c2e-9d43-0d6f1b2b9a10',
            'guide_id' => 'core.signup',
            'locale' => 'es',
            'audience' => 'site',
            'active' => true,
            'title' => 'Inscripción',
            'keywords' => ['apuntarse', 'mesa'],
            'summary' => 'Resumen',
            'body_text' => 'Cuerpo <b>',
            'body' => '# Cuerpo',
        ]);

        $this->assertSame([
            'id' => 'b6d7c7f4-1d1a-4c2e-9d43-0d6f1b2b9a10',
            'guide_id' => 'core.signup',
            'locale' => 'es',
            'audience' => 'site',
            'active' => true,
            'title' => 'Inscripción',
            'keywords' => 'apuntarse mesa',
            'summary' => 'Resumen',
            'body_text' => 'Cuerpo <b>',
        ], $model->toSearchableArray());
    }
}
