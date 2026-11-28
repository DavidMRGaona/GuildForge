<?php

declare(strict_types=1);

namespace Tests\Support\Bots;

use App\Application\Activity\Services\ActivityPublisherInterface;

final class RecordingActivityPublisher implements ActivityPublisherInterface
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    public function recorded(string $sourceType, string $subjectId): void
    {
        $this->calls[] = [$sourceType, $subjectId];
    }
}
