<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\AboutPageServiceInterface;
use Inertia\Inertia;
use Inertia\Response;

final class AboutController extends Controller
{
    public function __construct(
        private readonly AboutPageServiceInterface $aboutPage,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('About', $this->aboutPage->getAboutPageData());
    }
}
