<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Domain\Authorization\Enums\RoleAuditSource;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Who makes a change recorded in the role history, and from where.
 *
 * By default the signed-in user and the source of the current context
 * (RoleAuditSourceResolver). Work done for someone who is not signed in here imposes them
 * with actingFor(): a queued module update records who requested it, and its
 * module:finish-update process records the job's source and actor instead of its own.
 * Only the history reads this; nobody is signed in, so policies and module code are not
 * affected.
 *
 * A singleton, like the resolver: actingFor() restores the previous values, so the next
 * job in the same worker starts clean.
 */
final class RoleAuditContext
{
    private bool $actorImposed = false;

    private ?string $actorId = null;

    private ?RoleAuditSource $source = null;

    public function __construct(
        private readonly RoleAuditSourceResolver $resolver,
    ) {}

    public function actorId(): ?string
    {
        if ($this->actorImposed) {
            return $this->actorId;
        }

        $id = Auth::id();

        return is_scalar($id) ? (string) $id : null;
    }

    public function source(): RoleAuditSource
    {
        return $this->source ?? $this->resolver->resolve();
    }

    /**
     * Runs the callback with this actor (null: nobody) and, when given, this source.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function actingFor(?string $actorId, Closure $callback, ?RoleAuditSource $source = null): mixed
    {
        $previous = [$this->actorImposed, $this->actorId, $this->source];

        $this->actorImposed = true;
        $this->actorId = $actorId;
        $this->source = $source ?? $this->source;

        try {
            return $callback();
        } finally {
            [$this->actorImposed, $this->actorId, $this->source] = $previous;
        }
    }
}
