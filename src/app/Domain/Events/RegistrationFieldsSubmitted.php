<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * A person registered and the form carried fields added by modules (core#699). Dispatched right
 * after UserRegistered, once the account exists with its base role; only when at least one module
 * field was available. Values are keyed by field key (`<module>.<name>`), one per field that was
 * available and passed its checks, false when left unticked. Nothing else ever reaches them:
 * forged keys and fields that were unavailable or failed are dropped.
 *
 * Listen with a queued listener (`ShouldQueue`, `afterCommit = true`): the account is already
 * stored, but work that leaves the request (a mailing list, an external API) must not run inside
 * it, and the registration never waits for nor fails because of a listener.
 */
final readonly class RegistrationFieldsSubmitted
{
    /**
     * @param  array<string, bool>  $values
     */
    public function __construct(
        public string $userId,
        public array $values,
    ) {}

    /**
     * Whether the field with this key came ticked (false when unticked or not there).
     */
    public function ticked(string $key): bool
    {
        return ($this->values[$key] ?? false) === true;
    }
}
