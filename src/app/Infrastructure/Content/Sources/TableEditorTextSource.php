<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Sources;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\DTOs\EditorTextDTO;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Texts kept in one column of one table, read and written with the query builder: soft-deleted rows
 * count (they can be restored), and writing one fires no model events and leaves updated_at alone.
 * Implement type(), folder(), table() and column(); override scope() to leave rows out.
 */
abstract class TableEditorTextSource implements EditorTextSourceInterface
{
    /** Rows of text read at a time */
    private const int CHUNK_SIZE = 200;

    abstract protected function table(): string;

    abstract protected function column(): string;

    /**
     * The column that identifies a row.
     */
    protected function keyColumn(): string
    {
        return 'id';
    }

    /**
     * Narrows the rows whose texts are read and written (official guides, for instance, are not
     * written with the editor).
     */
    protected function scope(Builder $query): Builder
    {
        return $query;
    }

    public function texts(): iterable
    {
        $key = $this->keyColumn();
        $column = $this->column();
        $rows = $this->scope(DB::table($this->table())->whereNotNull($column))
            ->select([$key, $column])
            ->lazyById(self::CHUNK_SIZE, $key);

        foreach ($rows as $row) {
            $id = $row->{$key};
            $html = $row->{$column};

            if (! is_scalar($id) || ! is_string($html) || $html === '') {
                continue;
            }

            yield new EditorTextDTO((string) $id, $html, $this->table().'.'.$column.'#'.$id);
        }
    }

    public function replace(EditorTextDTO $text, string $html): bool
    {
        $column = $this->column();

        return $this->scope(DB::table($this->table()))
            ->where($this->keyColumn(), $text->id)
            ->where($column, $text->html)
            ->update([$column => $html]) === 1;
    }
}
