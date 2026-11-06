<?php

declare(strict_types=1);

namespace App\Filament\Resources\KnowledgeGuideResource;

use App\Application\Knowledge\DTOs\SiteGuideDTO;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use Throwable;

/**
 * Translates between the guide form's state and SiteGuideDTO.
 */
final class SiteGuideFormData
{
    public const string MEMBERS = 'members';

    public const string NON_MEMBERS = 'non_members';

    public const string ANYONE = 'any';

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(KnowledgeGuideModel $guide): array
    {
        $conditions = GuideConditions::fromArray($guide->conditions);

        return [
            'title' => $guide->title,
            'summary' => $guide->summary,
            'audience' => $guide->audience,
            'category_id' => $guide->category_id,
            'sort_order' => $guide->sort_order,
            'keywords' => $guide->keywords,
            'featured' => $guide->featured,
            'active' => $guide->active,
            'body' => $guide->body,
            'permissions_all' => $conditions->permissionsAll,
            'permissions_any' => $conditions->permissionsAny,
            'capabilities' => $conditions->capabilities,
            'negated_capabilities' => $conditions->negatedCapabilities,
            'association_member' => match ($conditions->associationMember) {
                true => self::MEMBERS,
                false => self::NON_MEMBERS,
                null => self::ANYONE,
            },
            'related' => $guide->related,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws SiteGuideException
     */
    public static function toDto(array $data): SiteGuideDTO
    {
        $audience = GuideAudience::tryFrom(self::string($data, 'audience'));
        if ($audience === null) {
            throw SiteGuideException::unknownCategory(self::string($data, 'category_id'), self::string($data, 'audience'));
        }

        return new SiteGuideDTO(
            title: self::string($data, 'title'),
            summary: self::string($data, 'summary'),
            audience: $audience,
            categoryId: self::string($data, 'category_id'),
            body: self::string($data, 'body'),
            keywords: self::strings($data, 'keywords'),
            order: is_numeric($data['sort_order'] ?? null) ? (int) $data['sort_order'] : 100,
            conditions: new GuideConditions(
                capabilities: self::strings($data, 'capabilities'),
                negatedCapabilities: self::strings($data, 'negated_capabilities'),
                permissionsAll: self::strings($data, 'permissions_all'),
                permissionsAny: self::strings($data, 'permissions_any'),
                associationMember: match ($data['association_member'] ?? null) {
                    self::MEMBERS => true,
                    self::NON_MEMBERS => false,
                    default => null,
                },
            ),
            related: self::related($data),
            featured: (bool) ($data['featured'] ?? false),
            published: (bool) ($data['active'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function strings(array $data, string $key): array
    {
        $values = $data[$key] ?? [];
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== '')));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<GuideId>
     *
     * @throws SiteGuideException
     */
    private static function related(array $data): array
    {
        $related = [];
        foreach (self::strings($data, 'related') as $id) {
            try {
                $related[] = GuideId::fromString($id);
            } catch (Throwable) {
                throw SiteGuideException::invalidRelated($id);
            }
        }

        return $related;
    }
}
