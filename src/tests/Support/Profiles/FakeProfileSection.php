<?php

declare(strict_types=1);

namespace Tests\Support\Profiles;

use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Domain\Profiles\ProfileRegion;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A profile section for tests: tests extend it in anonymous classes and override what they need.
 * Its props echo whom it was asked about and for whom.
 */
abstract class FakeProfileSection implements ProfileSectionInterface
{
    public function label(): string
    {
        return 'Sección de ejemplo';
    }

    public function description(): string
    {
        return 'Una sección para las pruebas.';
    }

    public function component(): string
    {
        return 'components/ExampleSection.vue';
    }

    public function region(): ProfileRegion
    {
        return ProfileRegion::Main;
    }

    public function order(): int
    {
        return 100;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function props(ProfileOwnerDTO $owner, ?Authenticatable $viewer): array
    {
        return [
            'owner' => $owner->handle,
            'signedIn' => $viewer instanceof Authenticatable,
        ];
    }
}
