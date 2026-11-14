<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

final class OtherFakeLinkableRecordSource extends FakeLinkableRecordSource
{
    public function type(): string
    {
        return 'other-fake';
    }
}
