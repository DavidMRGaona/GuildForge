<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AboutPageServiceInterface;
use Inertia\Inertia;
use Inertia\Response;

final class AboutController extends Controller
{
    public function __construct(
        private readonly AboutPageServiceInterface $aboutPage,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function __invoke(): Response
    {
        $about = $this->aboutPage->getAboutPageData();

        return Inertia::render('About', [
            ...$about->toArray(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.about', ['name' => $about->guildName]),
                description: $about->aboutTagline !== '' ? $about->aboutTagline : null,
                imagePublicId: $about->aboutHeroImage !== '' ? $about->aboutHeroImage : null,
            ))->toArray(),
        ]);
    }
}
