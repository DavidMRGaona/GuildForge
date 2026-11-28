<?php

declare(strict_types=1);

namespace Tests\Support\Bots;

use App\Application\Bots\Contracts\BotInterface;

/**
 * A valid bot of the `forum` module; tests subclass it to break one thing at a time.
 */
class FakeBot implements BotInterface
{
    public function key(): string
    {
        return 'forum.publisher';
    }

    public function label(): string
    {
        return 'Publicador del foro';
    }

    public function description(): string
    {
        return 'Publica avisos en el foro.';
    }

    public function name(): ?string
    {
        return null;
    }
}
