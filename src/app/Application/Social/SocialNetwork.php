<?php

declare(strict_types=1);

namespace App\Application\Social;

/**
 * Social networks the site can link to. The case value is the key used in the
 * `social_<network>` settings and in the `socialLinks` shared prop.
 */
enum SocialNetwork: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Twitter = 'twitter';
    case Discord = 'discord';
    case Tiktok = 'tiktok';
    case Bluesky = 'bluesky';
    case Telegram = 'telegram';
    case Whatsapp = 'whatsapp';
    case Youtube = 'youtube';
    case Twitch = 'twitch';

    public function settingKey(): string
    {
        return 'social_'.$this->value;
    }

    /**
     * Setting that says whether the network is shown on the public site (footer and about page).
     * A missing value means shown.
     */
    public function visibilityKey(): string
    {
        return 'social_'.$this->value.'_visible';
    }

    /**
     * Hosts the address must belong to, or null when any host is accepted.
     *
     * @return list<string>|null
     */
    public function allowedHosts(): ?array
    {
        return match ($this) {
            self::Whatsapp => ['chat.whatsapp.com', 'wa.me', 'www.whatsapp.com', 'whatsapp.com'],
            default => null,
        };
    }
}
