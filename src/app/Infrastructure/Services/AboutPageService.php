<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use JsonException;

final readonly class AboutPageService implements AboutPageServiceInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
    ) {
    }

    public function getAboutPageData(): array
    {
        return [
            'guildName' => $this->settings->get('guild_name', config('app.name')),
            'aboutHistory' => $this->settings->get('about_history', ''),
            'contactEmail' => $this->settings->get('contact_email', ''),
            'contactPhone' => $this->settings->get('contact_phone', ''),
            'contactAddress' => $this->settings->get('contact_address', ''),
            'aboutHeroImage' => $this->settings->get('about_hero_image', ''),
            'aboutTagline' => $this->settings->get('about_tagline', ''),
            'activities' => $this->parseActivities($this->settings->get('about_activities', '')),
            'joinSteps' => $this->parseJoinSteps($this->settings->get('join_steps', '')),
            'socialFacebook' => $this->formatSocialUrl($this->settings->get('social_facebook', '')),
            'socialInstagram' => $this->formatSocialUrl($this->settings->get('social_instagram', '')),
            'socialTwitter' => $this->formatSocialUrl($this->settings->get('social_twitter', '')),
            'socialDiscord' => $this->formatSocialUrl($this->settings->get('social_discord', '')),
            'socialTiktok' => $this->formatSocialUrl($this->settings->get('social_tiktok', '')),
        ];
    }

    public function parseActivities(mixed $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }

        try {
            $activities = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($activities)) {
                return [];
            }

            return array_values(array_filter(
                $activities,
                fn ($activity) => is_array($activity)
                    && isset($activity['icon'], $activity['title'], $activity['description'])
            ));
        } catch (JsonException) {
            return [];
        }
    }

    public function parseJoinSteps(mixed $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }

        try {
            $joinSteps = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($joinSteps)) {
                return [];
            }

            $filtered = array_filter(
                $joinSteps,
                fn ($step) => is_array($step) && isset($step['title'])
            );

            return array_values(array_map(
                fn (array $step): array => [
                    'title' => (string) $step['title'],
                    'description' => isset($step['description']) ? (string) $step['description'] : null,
                ],
                $filtered
            ));
        } catch (JsonException) {
            return [];
        }
    }

    public function formatSocialUrl(mixed $url): string
    {
        if (! is_string($url) || $url === '') {
            return '';
        }

        // If already has protocol, return as-is
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return 'https://'.$url;
    }
}
