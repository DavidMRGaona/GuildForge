<?php

declare(strict_types=1);

namespace App\Application\Services;

interface AboutPageServiceInterface
{
    /**
     * Get all data needed for the About page.
     *
     * @return array{
     *     guildName: string,
     *     aboutHistory: string,
     *     contactEmail: string,
     *     contactPhone: string,
     *     contactAddress: string,
     *     aboutHeroImage: string,
     *     aboutTagline: string,
     *     activities: array<int, array{icon: string, title: string, description: string}>,
     *     joinSteps: array<int, array{title: string, description: string|null}>,
     *     socialFacebook: string,
     *     socialInstagram: string,
     *     socialTwitter: string,
     *     socialDiscord: string,
     *     socialTiktok: string,
     * }
     */
    public function getAboutPageData(): array;

    /**
     * Parse activities JSON string into array.
     *
     * @return array<int, array{icon: string, title: string, description: string}>
     */
    public function parseActivities(mixed $json): array;

    /**
     * Parse join steps JSON string into array.
     *
     * @return array<int, array{title: string, description: string|null}>
     */
    public function parseJoinSteps(mixed $json): array;

    /**
     * Format social URL by prepending https:// if needed.
     */
    public function formatSocialUrl(mixed $url): string;
}
