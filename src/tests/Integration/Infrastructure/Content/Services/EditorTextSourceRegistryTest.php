<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Content\Services;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\Services\EditorTextSourceRegistryInterface;
use App\Infrastructure\Content\Services\EditorTextSourceRegistry;
use App\Infrastructure\Content\Sources\ArticleContentTextSource;
use stdClass;
use Tests\Support\Content\DuplicateNotesEditorTextSource;
use Tests\Support\Content\NotesEditorTextSource;
use Tests\Support\Content\UnbuildableEditorTextSource;
use Tests\TestCase;

/**
 * The editor text sources of the core and of the loaded modules (core#496).
 */
final class EditorTextSourceRegistryTest extends TestCase
{
    public function test_the_core_registers_its_texts_first(): void
    {
        $types = array_map(
            static fn (EditorTextSourceInterface $source): string => $source->type(),
            app(EditorTextSourceRegistryInterface::class)->sources(),
        );

        $this->assertSame(['events', 'articles', 'guides', 'about', 'legal'], $types);
    }

    public function test_a_module_source_is_added_and_removed_with_its_module(): void
    {
        $registry = new EditorTextSourceRegistry($this->app);
        $registry->register(ArticleContentTextSource::class);
        $registry->registerMany([NotesEditorTextSource::class], 'testing');

        $this->assertInstanceOf(NotesEditorTextSource::class, $registry->source(NotesEditorTextSource::TYPE));

        $registry->unregisterModule('testing');

        $this->assertNull($registry->source(NotesEditorTextSource::TYPE));
        $this->assertCount(1, $registry->sources());
    }

    public function test_classes_that_cannot_be_read_are_left_out_and_listed(): void
    {
        $registry = new EditorTextSourceRegistry($this->app);
        $registry->registerMany([UnbuildableEditorTextSource::class, stdClass::class, NotesEditorTextSource::class], 'testing');

        $this->assertSame([UnbuildableEditorTextSource::class, stdClass::class], $registry->unresolved());
        $this->assertCount(1, $registry->sources());
    }

    public function test_a_type_already_taken_leaves_the_second_source_out(): void
    {
        $registry = new EditorTextSourceRegistry($this->app);
        $registry->register(NotesEditorTextSource::class, 'first');
        $registry->register(DuplicateNotesEditorTextSource::class, 'second');

        $this->assertInstanceOf(NotesEditorTextSource::class, $registry->source(NotesEditorTextSource::TYPE));
        $this->assertSame([DuplicateNotesEditorTextSource::class], $registry->unresolved());
    }
}
