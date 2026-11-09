<?php

declare(strict_types=1);

namespace App\Domain\Publications\Events;

use InvalidArgumentException;

/**
 * A record offered through a publication source may have changed: published, rescheduled, filled
 * up or freed a place, closed its registrations, cancelled, ended or taken down.
 *
 * Dispatched by the record's owner once the change has committed, possibly more than once for the
 * same change. It carries no data on purpose: listeners read the record again from its source
 * (PublicationSourceInterface::find()), so events arriving late or out of order never bring back
 * an old state.
 */
final readonly class PublicationChanged
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
        if (trim($type) === '' || trim($id) === '') {
            throw new InvalidArgumentException('A publication change needs the type and the id.');
        }
    }
}
