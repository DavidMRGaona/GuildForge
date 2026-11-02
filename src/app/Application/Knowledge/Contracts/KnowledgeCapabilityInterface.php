<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

/**
 * A named condition guides use to show only what a person can do.
 *
 * Implementations reuse the domain rule that already decides the action.
 */
interface KnowledgeCapabilityInterface
{
    /**
     * Unique key, e.g. "game-tables.can_create_tables".
     */
    public function key(): string;

    /**
     * Whether the given user satisfies the condition.
     */
    public function passes(object $user): bool;
}
