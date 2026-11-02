<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;

final class DeniedKnowledgeCapability implements KnowledgeCapabilityInterface
{
    public function key(): string
    {
        return 'fake.denied';
    }

    public function passes(object $user): bool
    {
        return false;
    }
}
