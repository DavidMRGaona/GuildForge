<?php

declare(strict_types=1);

namespace Tests\Support\SiteBlocks;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A site block for tests: tests extend it in anonymous classes and override what they need.
 * Its props echo what it was given, so a test can see the settings and the person.
 */
abstract class FakeSiteBlock implements SiteBlockInterface
{
    public function label(): string
    {
        return 'Bloque de ejemplo';
    }

    public function placements(): array
    {
        return [SiteBlockPlacement::Footer, SiteBlockPlacement::Popup];
    }

    public function settingsSchema(): array
    {
        return [];
    }

    public function component(): string
    {
        return 'components/ExampleBlock.vue';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        return [
            'settings' => $settings,
            'signedIn' => $user instanceof Authenticatable,
        ];
    }
}
