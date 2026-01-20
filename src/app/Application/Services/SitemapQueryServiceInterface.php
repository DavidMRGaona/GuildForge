<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\SitemapEntryDTO;
use Illuminate\Support\Collection;

interface SitemapQueryServiceInterface
{
    /**
     * Get all sitemap entries.
     *
     * @return Collection<int, SitemapEntryDTO>
     */
    public function getAllEntries(): Collection;

    /**
     * Get static page entries (home, about, events index, articles index, gallery index).
     *
     * @return Collection<int, SitemapEntryDTO>
     */
    public function getStaticEntries(): Collection;

    /**
     * Get published events as sitemap entries.
     *
     * @return Collection<int, SitemapEntryDTO>
     */
    public function getEventEntries(): Collection;

    /**
     * Get published articles as sitemap entries.
     *
     * @return Collection<int, SitemapEntryDTO>
     */
    public function getArticleEntries(): Collection;

    /**
     * Get published galleries as sitemap entries.
     *
     * @return Collection<int, SitemapEntryDTO>
     */
    public function getGalleryEntries(): Collection;
}
