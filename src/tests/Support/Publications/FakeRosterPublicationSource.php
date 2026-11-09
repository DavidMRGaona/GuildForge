<?php

declare(strict_types=1);

namespace Tests\Support\Publications;

use App\Application\Publications\Contracts\PublicationRosterInterface;
use App\Application\Publications\DTOs\PublicationRosterDTO;

/**
 * Publication source that also offers who takes part, as a campaign module would.
 */
final class FakeRosterPublicationSource extends FakePublicationSource implements PublicationRosterInterface
{
    public function type(): string
    {
        return 'fake-roster';
    }

    public function roster(string $id): ?PublicationRosterDTO
    {
        return null;
    }

    public function rostersOf(string $userId): array
    {
        return [];
    }
}
