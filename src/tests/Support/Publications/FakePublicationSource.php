<?php

declare(strict_types=1);

namespace Tests\Support\Publications;

use App\Application\Publications\Contracts\PublicationSourceInterface;
use App\Application\Publications\DTOs\PublicationDTO;

/**
 * Publication source used in tests to simulate a module offering publications.
 */
class FakePublicationSource implements PublicationSourceInterface
{
    public function type(): string
    {
        return 'fake';
    }

    public function find(string $id): ?PublicationDTO
    {
        return null;
    }

    public function publishedIds(): iterable
    {
        return [];
    }
}
