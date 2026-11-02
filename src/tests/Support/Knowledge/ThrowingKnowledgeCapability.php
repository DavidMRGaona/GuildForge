<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use RuntimeException;

final class ThrowingKnowledgeCapability implements KnowledgeCapabilityInterface
{
    public function key(): string
    {
        return 'fake.throws';
    }

    public function passes(object $user): bool
    {
        throw new RuntimeException('boom');
    }
}
