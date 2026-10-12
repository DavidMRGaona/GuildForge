<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use Inertia\Inertia;
use Inertia\Response;

final class CalendarPageController extends Controller
{
    public function __construct(
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Calendar/Index', [
            'seo' => $this->seo->forPage(new SeoPageDTO(title: (string) __('seo.titles.calendar')))->toArray(),
        ]);
    }
}
