<?php

declare(strict_types=1);

namespace Tests\Support\Publications;

final class OtherFakePublicationSource extends FakePublicationSource
{
    public function type(): string
    {
        return 'other-fake';
    }
}
