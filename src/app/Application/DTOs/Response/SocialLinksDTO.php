<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class SocialLinksDTO
{
    public function __construct(
        public string $facebook,
        public string $instagram,
        public string $twitter,
        public string $discord,
        public string $tiktok,
    ) {
    }

    /**
     * Convert to array for frontend consumption.
     *
     * @return array{facebook: string, instagram: string, twitter: string, discord: string, tiktok: string}
     */
    public function toArray(): array
    {
        return [
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'twitter' => $this->twitter,
            'discord' => $this->discord,
            'tiktok' => $this->tiktok,
        ];
    }
}
