<?php

declare(strict_types=1);

namespace App\Infrastructure\Social;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Social\SocialNetwork;
use App\Application\Social\SocialUrl;

/**
 * Reads the site's social networks from the settings (`social_<network>` and its
 * `social_<network>_visible` switch).
 */
final readonly class SocialLinkResolver
{
    public function __construct(private SettingsServiceInterface $settings) {}

    /**
     * The network's https address, or '' when none is stored or the stored one is not a valid address.
     */
    public function address(SocialNetwork $network): string
    {
        return SocialUrl::normalize($this->settings->get($network->settingKey(), ''), $network);
    }

    /**
     * Whether the network is shown on the public site. A switch never saved means shown.
     */
    public function isVisible(SocialNetwork $network): bool
    {
        $value = $this->settings->get($network->visibilityKey());

        return in_array($value, [null, '1', 'true'], true);
    }

    /**
     * The address to show to visitors: '' when the network is unchecked or has no valid address.
     */
    public function publicAddress(SocialNetwork $network): string
    {
        return $this->isVisible($network) ? $this->address($network) : '';
    }

    /**
     * @return array<string, string> Public address by network key, '' for the ones not shown.
     */
    public function publicAddresses(): array
    {
        $addresses = [];

        foreach (SocialNetwork::cases() as $network) {
            $addresses[$network->value] = $this->publicAddress($network);
        }

        return $addresses;
    }
}
