<?php

declare(strict_types=1);

namespace App\Infrastructure\Factories;

use App\Application\DTOs\Response\ArticleResponseDTO;
use App\Application\DTOs\Response\AuthorResponseDTO;
use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\Response\GalleryDetailResponseDTO;
use App\Application\DTOs\Response\GalleryResponseDTO;
use App\Application\DTOs\Response\HeroSlideResponseDTO;
use App\Application\DTOs\Response\PhotoResponseDTO;
use App\Application\DTOs\Response\TagResponseDTO;
use App\Application\DTOs\Response\UserResponseDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Support\SanitizesHtml;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class EloquentResponseDTOFactory implements ResponseDTOFactoryInterface
{
    use SanitizesHtml;

    public function createEventDTO(object $model): EventResponseDTO
    {
        if (! $model instanceof EventModel) {
            throw new InvalidArgumentException('Expected EventModel instance');
        }

        $tags = $model->relationLoaded('tags')
            ? $model->tags->map(fn ($tag) => $this->createTagDTO($tag))->toArray()
            : [];

        return new EventResponseDTO(
            id: $model->id,
            title: $model->title,
            slug: $model->slug,
            description: $this->sanitizeHtml($model->description),
            startDate: DateTimeImmutable::createFromMutable($model->start_date),
            endDate: DateTimeImmutable::createFromMutable($model->end_date),
            location: $model->location,
            memberPrice: $model->member_price !== null ? (float) $model->member_price : null,
            nonMemberPrice: $model->non_member_price !== null ? (float) $model->non_member_price : null,
            imagePublicId: $model->image_public_id,
            isPublished: $model->is_published,
            createdAt: $model->created_at !== null
                ? DateTimeImmutable::createFromMutable($model->created_at)
                : null,
            updatedAt: $model->updated_at !== null
                ? DateTimeImmutable::createFromMutable($model->updated_at)
                : null,
            tags: $tags,
        );
    }

    public function createArticleDTO(object $model): ArticleResponseDTO
    {
        if (! $model instanceof ArticleModel) {
            throw new InvalidArgumentException('Expected ArticleModel instance');
        }

        $tags = $model->relationLoaded('tags')
            ? $model->tags->map(fn ($tag) => $this->createTagDTO($tag))->toArray()
            : [];

        return new ArticleResponseDTO(
            id: $model->id,
            title: $model->title,
            slug: $model->slug,
            content: $this->sanitizeHtml($model->content),
            excerpt: $model->excerpt,
            featuredImage: $model->featured_image_public_id,
            isPublished: $model->is_published,
            publishedAt: $model->published_at !== null
                ? DateTimeImmutable::createFromMutable($model->published_at)
                : null,
            author: $model->relationLoaded('author') && $model->author !== null // @phpstan-ignore-line notIdentical.alwaysTrue
                ? $this->createAuthorDTO($model->author)
                : null,
            createdAt: $model->created_at !== null
                ? DateTimeImmutable::createFromMutable($model->created_at)
                : null,
            updatedAt: $model->updated_at !== null
                ? DateTimeImmutable::createFromMutable($model->updated_at)
                : null,
            tags: $tags,
        );
    }

    public function createAuthorDTO(object $model): AuthorResponseDTO
    {
        if (! $model instanceof UserModel) {
            throw new InvalidArgumentException('Expected UserModel instance');
        }

        return new AuthorResponseDTO(
            id: (string) $model->id,
            name: $model->name,
            displayName: $model->display_name ?? $model->name,
            avatarPublicId: $model->avatar_public_id,
        );
    }

    public function createGalleryDTO(object $model): GalleryResponseDTO
    {
        if (! $model instanceof GalleryModel) {
            throw new InvalidArgumentException('Expected GalleryModel instance');
        }

        $tags = $model->relationLoaded('tags')
            ? $model->tags->map(fn ($tag) => $this->createTagDTO($tag))->toArray()
            : [];

        return new GalleryResponseDTO(
            id: $model->id,
            title: $model->title,
            slug: $model->slug,
            description: $model->description,
            coverImagePublicId: $model->cover_image_public_id,
            isPublished: $model->is_published,
            isFeatured: $model->is_featured,
            photoCount: $model->photos_count ?? $model->photos()->count(),
            createdAt: $model->created_at !== null
                ? DateTimeImmutable::createFromMutable($model->created_at)
                : null,
            updatedAt: $model->updated_at !== null
                ? DateTimeImmutable::createFromMutable($model->updated_at)
                : null,
            tags: $tags,
        );
    }

    public function createGalleryDetailDTO(object $model): GalleryDetailResponseDTO
    {
        if (! $model instanceof GalleryModel) {
            throw new InvalidArgumentException('Expected GalleryModel instance');
        }

        $photos = $model->relationLoaded('photos')
            ? $model->photos->map(fn ($photo) => $this->createPhotoDTO($photo))->toArray()
            : [];

        $tags = $model->relationLoaded('tags')
            ? $model->tags->map(fn ($tag) => $this->createTagDTO($tag))->toArray()
            : [];

        return new GalleryDetailResponseDTO(
            id: $model->id,
            title: $model->title,
            slug: $model->slug,
            description: $model->description,
            isPublished: $model->is_published,
            isFeatured: $model->is_featured,
            photos: $photos,
            createdAt: $model->created_at !== null
                ? DateTimeImmutable::createFromMutable($model->created_at)
                : null,
            updatedAt: $model->updated_at !== null
                ? DateTimeImmutable::createFromMutable($model->updated_at)
                : null,
            tags: $tags,
        );
    }

    public function createPhotoDTO(object $model): PhotoResponseDTO
    {
        if (! $model instanceof PhotoModel) {
            throw new InvalidArgumentException('Expected PhotoModel instance');
        }

        return new PhotoResponseDTO(
            id: $model->id,
            imagePublicId: $model->image_public_id,
            caption: $model->caption,
            sortOrder: $model->sort_order,
        );
    }

    public function createHeroSlideDTO(object $model): HeroSlideResponseDTO
    {
        if (! $model instanceof HeroSlideModel) {
            throw new InvalidArgumentException('Expected HeroSlideModel instance');
        }

        return new HeroSlideResponseDTO(
            id: $model->id,
            title: $model->title,
            subtitle: $model->subtitle,
            buttonText: $model->button_text,
            buttonUrl: $model->button_url,
            imagePublicId: $model->image_public_id ?? '',
            isActive: $model->is_active,
            sortOrder: $model->sort_order,
        );
    }

    public function createTagDTO(object $model): TagResponseDTO
    {
        if (! $model instanceof TagModel) {
            throw new InvalidArgumentException('Expected TagModel instance');
        }

        return new TagResponseDTO(
            id: $model->id,
            name: $model->name,
            slug: $model->slug,
            parentId: $model->parent_id,
            parentName: $model->relationLoaded('parent') && $model->parent !== null
                ? $model->parent->name
                : null,
            appliesTo: $model->applies_to,
            color: $model->color,
            sortOrder: $model->sort_order,
        );
    }

    public function createUserDTO(object $model): UserResponseDTO
    {
        if (! $model instanceof UserModel) {
            throw new InvalidArgumentException('Expected UserModel instance');
        }

        return new UserResponseDTO(
            id: (string) $model->id,
            name: $model->name,
            displayName: $model->display_name,
            email: $model->email,
            pendingEmail: $model->pending_email,
            avatarPublicId: $model->avatar_public_id,
            role: ($model->role ?? UserRole::Member)->value,
            emailVerified: $model->email_verified_at !== null,
            createdAt: $model->created_at !== null
                ? DateTimeImmutable::createFromMutable($model->created_at)
                : new DateTimeImmutable,
        );
    }
}
