<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Event;
use App\Domain\ValueObjects\EventId;
use DateTimeImmutable;
use Illuminate\Support\Collection;

interface EventRepositoryInterface
{
    public function findById(EventId $id): ?Event;

    public function findBySlug(string $slug): ?Event;

    /**
     * @return Collection<int, Event>
     */
    public function findUpcoming(int $limit = 10): Collection;

    /**
     * @return Collection<int, Event>
     */
    public function findPublished(): Collection;

    /**
     * @return Collection<int, Event>
     */
    public function findByDateRange(DateTimeImmutable $start, DateTimeImmutable $end): Collection;

    public function save(Event $event): void;

    public function delete(Event $event): void;
}
