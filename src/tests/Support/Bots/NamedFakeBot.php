<?php

declare(strict_types=1);

namespace Tests\Support\Bots;

/**
 * A valid bot that declares a public name of its own.
 */
final class NamedFakeBot extends FakeBot
{
    public function name(): string
    {
        return 'Heraldo';
    }
}
