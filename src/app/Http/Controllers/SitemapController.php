<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\SitemapQueryServiceInterface;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __construct(
        private readonly SitemapQueryServiceInterface $sitemapQuery,
    ) {}

    public function __invoke(): Response
    {
        $urls = $this->sitemapQuery->getAllEntries()
            ->map(fn ($entry) => $entry->toArray());

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
