<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Turns a public profile into what the browser draws, for one person (core#742). It does not decide
 * whether the person may see the profile at all, so only the core's own profile page calls it, once
 * the access rule has let the person see it; modules never call it.
 *
 * The owner's layout decides order and on/off; a section or stat it does not mention takes its own
 * default (after the ones it orders) and a key it holds that nobody registers is ignored. It fails
 * closed and never throws: a section or stat that cannot be drawn is left out, and anything unexpected
 * (a key in the layout nobody registers, a check, props() or stat() that throws, props not fit for the
 * browser, a negative count or a link that is not allowed) is logged once per key and reason for a
 * day. A section that throws NothingToDrawException, or a stat that returns null, is left out quietly.
 * Props and exception messages are never logged (an exception only by its class).
 */
interface ProfileRendererInterface
{
    public function render(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO;
}
