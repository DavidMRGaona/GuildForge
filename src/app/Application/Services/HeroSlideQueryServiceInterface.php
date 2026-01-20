<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\HeroSlideResponseDTO;

interface HeroSlideQueryServiceInterface
{
    /**
     * @return array<int, HeroSlideResponseDTO>
     */
    public function getActiveSlides(): array;
}
