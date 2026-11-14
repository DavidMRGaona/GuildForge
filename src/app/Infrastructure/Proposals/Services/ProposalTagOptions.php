<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\DTOs\Response\TagResponseDTO;
use App\Application\Proposals\Services\ProposalTagOptionsInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

final readonly class ProposalTagOptions implements ProposalTagOptionsInterface
{
    public function __construct(
        private TagQueryServiceInterface $tags,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function additionalTags(ProposalType $type): array
    {
        return array_values(array_filter($this->tags->getByType($type->value), static fn (TagResponseDTO $tag): bool => $tag->parentId !== null));
    }

    public function categoriesFor(ProposalType $type, string $userId): array
    {
        $user = UserModel::query()->find($userId);

        if (! $user instanceof UserModel || ! $this->authorization->can($user, $type->publishDirectlyPermission())) {
            return [];
        }

        return $this->categories($type);
    }

    public function hasCategories(ProposalType $type): bool
    {
        return $this->categories($type) !== [];
    }

    /**
     * @return list<TagResponseDTO>
     */
    private function categories(ProposalType $type): array
    {
        return array_values(array_filter($this->tags->getByType($type->value), static fn (TagResponseDTO $tag): bool => $tag->parentId === null));
    }
}
