<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;

class FakeKnowledgeCapability implements KnowledgeCapabilityInterface
{
    public function key(): string
    {
        return 'fake.can_do_it';
    }

    public function passes(object $user): bool
    {
        return true;
    }
}
