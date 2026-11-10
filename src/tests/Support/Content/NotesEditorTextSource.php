<?php

declare(strict_types=1);

namespace Tests\Support\Content;

use App\Domain\Content\ValueObjects\EditorImageFolder;
use App\Infrastructure\Content\Sources\TableEditorTextSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A module's texts, as a module registers them: the body of the rows of "test_notes", whose images
 * move to "notes/content/".
 */
final class NotesEditorTextSource extends TableEditorTextSource
{
    public const string TYPE = 'testing.notes';

    public const string TABLE = 'test_notes';

    public static function createTable(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('body')->nullable();
            $table->softDeletes();
        });
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageFolder::of('notes');
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function column(): string
    {
        return 'body';
    }
}
