<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\HeroSlideQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;

final readonly class HeroSlideQueryService implements HeroSlideQueryServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
    ) {}

    public function getActiveSlides(): array
    {
        $slides = HeroSlideModel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $slides->map(fn (HeroSlideModel $slide) => $this->dtoFactory->createHeroSlideDTO($slide))->all();
    }
}
