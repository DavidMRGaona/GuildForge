<?php

declare(strict_types=1);

namespace App\Application\Factories;

use App\Application\DTOs\Response\ArticleResponseDTO;
use App\Application\DTOs\Response\AuthorResponseDTO;
use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\Response\GalleryDetailResponseDTO;
use App\Application\DTOs\Response\GalleryResponseDTO;
use App\Application\DTOs\Response\HeroSlideResponseDTO;
use App\Application\DTOs\Response\PhotoResponseDTO;

interface ResponseDTOFactoryInterface
{
    /**
     * @param object $model The underlying model (implementation-specific)
     */
    public function createEventDTO(object $model): EventResponseDTO;

    /**
     * @param object $model The underlying model (implementation-specific)
     */
    public function createArticleDTO(object $model): ArticleResponseDTO;

    /**
     * @param object $model The underlying model (implementation-specific)
     */
    public function createAuthorDTO(object $model): AuthorResponseDTO;

    /**
     * @param object $model The underlying model (implementation-specific)
     */
    public function createGalleryDTO(object $model): GalleryResponseDTO;

    /**
     * @param object $model The underlying model (implementation-specific)
     */
    public function createGalleryDetailDTO(object $model): GalleryDetailResponseDTO;

    /**
     * @param object $model The underlying model (implementation-specific)
     */
    public function createPhotoDTO(object $model): PhotoResponseDTO;

    /**
     * @param object $model The underlying model (implementation-specific)
     */
    public function createHeroSlideDTO(object $model): HeroSlideResponseDTO;
}
