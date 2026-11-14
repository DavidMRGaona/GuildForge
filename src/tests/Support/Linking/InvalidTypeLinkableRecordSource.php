<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

final class InvalidTypeLinkableRecordSource extends FakeLinkableRecordSource
{
    public function type(): string
    {
        return 'Not_Kebab';
    }
}
