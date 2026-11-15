<?php

declare(strict_types=1);

namespace Tests\Support\Profiles;

use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A profile stat for tests: tests extend it in anonymous classes and override what they need.
 * It counts 7 for a signed-in viewer and 3 for a guest.
 */
abstract class FakeProfileStat implements ProfileStatInterface
{
    public function label(): string
    {
        return 'Contador de ejemplo';
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

    public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO
    {
        return new ProfileStatDTO($viewer instanceof Authenticatable ? 7 : 3, '/miembros/'.$owner->handle);
    }
}
