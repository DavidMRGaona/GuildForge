<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\SlugRedirect;
use App\Domain\Repositories\SlugRedirectRepositoryInterface;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use Illuminate\Support\Str;

final readonly class SlugRedirectService implements SlugRedirectServiceInterface
{
    public function __construct(
        private SlugRedirectRepositoryInterface $repository,
    ) {
    }

    public function resolveCurrentSlug(string $slug, string $entityType): ?string
    {
        $redirect = $this->repository->findByOldSlugAndType(
            new Slug($slug),
            $entityType,
        );

        return $redirect?->newSlug()->value;
    }

    public function handleSlugChange(
        string $oldSlug,
        string $newSlug,
        string $entityType,
        string $entityId,
    ): void {
        if ($oldSlug === $newSlug) {
            return;
        }

        $oldSlugVO = new Slug($oldSlug);
        $newSlugVO = new Slug($newSlug);

        $existing = $this->repository->findByOldSlugAndType($oldSlugVO, $entityType);

        if ($existing !== null) {
            $this->repository->save($existing->updateTarget($newSlugVO));
        } else {
            $redirect = new SlugRedirect(
                id: (string) Str::uuid(),
                oldSlug: $oldSlugVO,
                newSlug: $newSlugVO,
                entityType: $entityType,
                entityId: $entityId,
                createdAt: new DateTimeImmutable(),
            );
            $this->repository->save($redirect);
        }

        $this->repository->updateAllPointingTo($oldSlugVO, $newSlugVO, $entityType);
    }
}
