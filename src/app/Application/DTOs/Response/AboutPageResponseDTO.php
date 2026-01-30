<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class AboutPageResponseDTO
{
    /**
     * @param  array<ActivityDTO>  $activities
     * @param  array<JoinStepDTO>  $joinSteps
     */
    public function __construct(
        public string $guildName,
        public string $aboutHistory,
        public string $contactEmail,
        public string $contactPhone,
        public string $contactAddress,
        public string $aboutHeroImage,
        public string $aboutTagline,
        public array $activities,
        public array $joinSteps,
        public SocialLinksDTO $socialLinks,
        public LocationSettingsDTO $location,
    ) {
    }

    /**
     * Convert to array for frontend consumption.
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
     *     location: array{name: string, address: string, lat: float, lng: float, zoom: int},
     * }
     */
    public function toArray(): array
    {
        return [
            'guildName' => $this->guildName,
            'aboutHistory' => $this->aboutHistory,
            'contactEmail' => $this->contactEmail,
            'contactPhone' => $this->contactPhone,
            'contactAddress' => $this->contactAddress,
            'aboutHeroImage' => $this->aboutHeroImage,
            'aboutTagline' => $this->aboutTagline,
            'activities' => array_map(
                fn (ActivityDTO $activity): array => $activity->toArray(),
                $this->activities
            ),
            'joinSteps' => array_map(
                fn (JoinStepDTO $step): array => $step->toArray(),
                $this->joinSteps
            ),
            'socialFacebook' => $this->socialLinks->facebook,
            'socialInstagram' => $this->socialLinks->instagram,
            'socialTwitter' => $this->socialLinks->twitter,
            'socialDiscord' => $this->socialLinks->discord,
            'socialTiktok' => $this->socialLinks->tiktok,
            'location' => $this->location->toArray(),
        ];
    }
}
