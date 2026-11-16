<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileEditorDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The owner editing their public profile from the profile itself (core#743).
 *
 * Only the owner edits it, nobody else, administrators included: the editor and the routes that save
 * ask ownerId() first. During an impersonation the request's user is the account impersonated, so its
 * own rules (read-only refuses every save) apply.
 */
interface ProfileEditorInterface
{
    /**
     * The id of the signed-in person when this is the current address of their own profile; null for
     * anybody else, for an old or differently written address, and for a deactivated or anonymized
     * account. Fails closed: a check that throws is null (and logged).
     */
    public function ownerId(string $handle, ?Authenticatable $user): ?string;

    /**
     * What the editor starts from, or null when the account has no profile. Never call it for anybody
     * but the owner (ownerId()).
     */
    public function editorFor(string $userId): ?ProfileEditorDTO;
}
