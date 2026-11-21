<?php

declare(strict_types=1);

namespace Tests\Support\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Domain\Activity\ActivityKind;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An activity source with nothing to report; tests extend it to change what they need.
 */
class FakeActivitySource implements ActivitySourceInterface
{
    public function type(): string
    {
        return 'example-module.thing';
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::GameSession;
    }

    public function label(): string
    {
        return 'Cosas de ejemplo';
    }

    public function facts(string $subjectId): array
    {
        return [];
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        return new ActivityHistoryPageDTO([], null);
    }

    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        return [];
    }
}
