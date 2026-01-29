<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\LegalPageServiceInterface;
use Inertia\Inertia;
use Inertia\Response;

final class LegalPageController extends Controller
{
    public function __construct(
        private readonly LegalPageServiceInterface $legalPageService,
    ) {
    }

    public function show(string $slug): Response
    {
        $page = $this->legalPageService->getPublishedPage($slug);

        if ($page === null) {
            abort(404);
        }

        return Inertia::render('Legal/Show', [
            'title' => $page->title,
            'content' => $page->content,
            'lastUpdated' => $page->lastUpdated?->format('c'),
        ]);
    }
}
