<?php

declare(strict_types=1);

namespace Tests\Support\Content;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\DTOs\EditorTextDTO;
use App\Domain\Content\ValueObjects\EditorImageFolder;
use RuntimeException;

/**
 * A source the container cannot build, as when one of its dependencies is missing.
 */
final class UnbuildableEditorTextSource implements EditorTextSourceInterface
{
    public function __construct()
    {
        throw new RuntimeException('Missing dependency');
    }

    public function type(): string
    {
        return 'testing.unbuildable';
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageFolder::of('unbuildable');
    }

    public function texts(): iterable
    {
        return [];
    }

    public function replace(EditorTextDTO $text, string $html): bool
    {
        return false;
    }
}
