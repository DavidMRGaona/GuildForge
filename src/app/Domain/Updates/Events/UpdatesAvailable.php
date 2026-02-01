<?php

declare(strict_types=1);

namespace App\Domain\Updates\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class UpdatesAvailable
{
    use Dispatchable;

    /**
     * @param  array<string, array{current: string, available: string}>  $moduleUpdates
     */
    public function __construct(
        public array $moduleUpdates,
        public ?string $coreUpdate = null,
    ) {}

    public function hasUpdates(): bool
    {
        return ! empty($this->moduleUpdates) || $this->coreUpdate !== null;
    }

    public function count(): int
    {
        $count = count($this->moduleUpdates);

        if ($this->coreUpdate !== null) {
            $count++;
        }

        return $count;
    }
}
