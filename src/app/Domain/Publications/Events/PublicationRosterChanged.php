<?php

declare(strict_types=1);

namespace App\Domain\Publications\Events;

use App\Domain\Publications\UserIds;
use InvalidArgumentException;

/**
 * Someone joined or left a publication's roster, or started or stopped leading it. Dispatched by
 * the owner once the change commits. It names the accounts affected and nothing else: listeners
 * read the roster again (PublicationRosterInterface), so late or out-of-order deliveries never
 * bring back an old one.
 */
final readonly class PublicationRosterChanged
{
    /** @var list<string> */
    public array $userIds;

    /**
     * @param  list<string>  $userIds  Accounts whose place in the roster changed
     */
    public function __construct(
        public string $type,
        public string $id,
        array $userIds,
    ) {
        if (trim($type) === '' || trim($id) === '') {
            throw new InvalidArgumentException('A publication roster change needs the type and the id.');
        }

        $this->userIds = UserIds::normalize($userIds, 'roster change');

        if ($this->userIds === []) {
            throw new InvalidArgumentException('A publication roster change needs the accounts it affects.');
        }
    }
}
