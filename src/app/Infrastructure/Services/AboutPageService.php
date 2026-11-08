<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\AboutPageResponseDTO;
use App\Application\DTOs\Response\ActivityDTO;
use App\Application\DTOs\Response\JoinStepDTO;
use App\Application\DTOs\Response\SocialLinksDTO;
use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Social\SocialNetwork;
use App\Application\Social\SocialUrl;
use App\Infrastructure\Social\SocialLinkResolver;
use App\Infrastructure\Support\SanitizesHtml;
use JsonException;

final readonly class AboutPageService implements AboutPageServiceInterface
{
    use SanitizesHtml;

    public function __construct(
        private SettingsServiceInterface $settings,
        private SiteNameResolver $siteName,
        private SocialLinkResolver $socialLinks,
    ) {}

    public function getAboutPageData(): AboutPageResponseDTO
    {
        $links = $this->socialLinks->publicAddresses();

        return new AboutPageResponseDTO(
            guildName: $this->siteName->resolve(),
            aboutHistory: $this->sanitizeHtml((string) $this->settings->get('about_history', '')),
            contactEmail: $this->settings->get('contact_email', ''),
            contactPhone: $this->settings->get('contact_phone', ''),
            contactAddress: $this->settings->get('contact_address', ''),
            aboutHeroImage: $this->settings->get('about_hero_image', ''),
            aboutTagline: $this->settings->get('about_tagline', ''),
            activities: $this->parseActivities($this->settings->get('about_activities', '')),
            joinSteps: $this->parseJoinSteps($this->settings->get('join_steps', '')),
            socialLinks: new SocialLinksDTO(
                facebook: $links[SocialNetwork::Facebook->value],
                instagram: $links[SocialNetwork::Instagram->value],
                twitter: $links[SocialNetwork::Twitter->value],
                discord: $links[SocialNetwork::Discord->value],
                tiktok: $links[SocialNetwork::Tiktok->value],
                bluesky: $links[SocialNetwork::Bluesky->value],
                telegram: $links[SocialNetwork::Telegram->value],
                whatsapp: $links[SocialNetwork::Whatsapp->value],
                youtube: $links[SocialNetwork::Youtube->value],
                twitch: $links[SocialNetwork::Twitch->value],
            ),
            location: $this->settings->getLocationSettings(),
        );
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

            $filtered = array_filter(
                $activities,
                fn ($activity) => is_array($activity)
                    && isset($activity['icon'], $activity['title'], $activity['description'])
            );

            return array_values(array_map(
                ActivityDTO::fromArray(...),
                $filtered
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
                fn (array $step): JoinStepDTO => JoinStepDTO::fromArray([
                    'title' => (string) $step['title'],
                    'description' => isset($step['description']) ? (string) $step['description'] : null,
                ]),
                $filtered
            ));
        } catch (JsonException) {
            return [];
        }
    }

    public function formatSocialUrl(mixed $url): string
    {
        return SocialUrl::normalize($url);
    }
}
