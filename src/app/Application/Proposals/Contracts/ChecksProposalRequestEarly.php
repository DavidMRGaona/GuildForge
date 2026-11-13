<?php

declare(strict_types=1);

namespace App\Application\Proposals\Contracts;

use App\Application\Proposals\DTOs\ProposedEventDTO;

/**
 * Optional, implemented by a module request next to EventProposalRequestInterface (core#612): it
 * lets the request tell whoever proposes an event that what they ask for probably cannot be
 * applied («el espacio no está libre en esas fechas»). A warning never blocks saving or
 * submitting and reserves nothing; the real check stays in apply(), inside the approval.
 *
 * The core asks again every time the proposal is shown (form and «Mis propuestas»), with values
 * already checked by ProposalRequestValueRule, so the answer follows what changed since. It fails
 * closed to «no warning»: a call that throws is logged (never the values) and leaves no warning.
 * Guide: docs/modules/proposal-requests.md.
 */
interface ChecksProposalRequestEarly
{
    /**
     * Plain, translated sentences for the author (the core escapes them); empty when there is
     * nothing to say. Must only read: no writes, no reservations.
     *
     * @param  array<string, bool|int|string|null>  $values  every field of fields(), checked and cast
     * @return list<string>
     */
    public function warnings(ProposedEventDTO $event, array $values): array;
}
