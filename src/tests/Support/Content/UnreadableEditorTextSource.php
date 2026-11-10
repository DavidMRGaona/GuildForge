<?php

declare(strict_types=1);

namespace Tests\Support\Content;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\DTOs\EditorTextDTO;
use App\Domain\Content\ValueObjects\EditorImageFolder;
use RuntimeException;

/**
 * A source whose texts cannot be read, as when its table is missing.
 */
final class UnreadableEditorTextSource implements EditorTextSourceInterface
{
    public const string TYPE = 'testing.unreadable';

    public function type(): string
    {
        return self::TYPE;
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageFolder::of('unreadable');
    }

    public function texts(): iterable
    {
        yield new EditorTextDTO('1', '<p><img src="/storage/never.png"></p>', 'unreadable#1');

        throw new RuntimeException('Table missing');
    }

    public function replace(EditorTextDTO $text, string $html): bool
    {
        throw new RuntimeException('Table missing');
    }
}
