<?php

declare(strict_types=1);

namespace Tests\Support\Publications;

final class InvalidTypePublicationSource extends FakePublicationSource
{
    public function type(): string
    {
        return 'Not Kebab';
    }
}
