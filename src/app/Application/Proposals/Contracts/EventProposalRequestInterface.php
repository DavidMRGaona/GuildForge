<?php

declare(strict_types=1);

namespace App\Application\Proposals\Contracts;

use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\ValueObjects\EventId;

/**
 * Something a module lets people ask for when they propose an event from the web (core#581):
 * «¿Quieres inscripciones?», «¿Habrá mesas de rol?». The person ticks the question and fills the
 * fields; whoever reviews confirms or adjusts the values and, on approval, apply() creates the
 * module's real configuration for the event.
 *
 * Modules return their implementations from ModuleServiceProvider::registerEventProposalRequests().
 * The core draws the form from fields() (no module Vue), checks every value on the server, stores
 * them with the proposal (`event_proposal_requests`) and shows them in «Mis propuestas», in the
 * panel and in the notices. Only requests of enabled modules exist; a stored request whose module
 * is gone blocks the approval until the reviewer unticks it. Guide: docs/modules/proposal-requests.md.
 *
 * Values are `array<string, bool|int|string|null>` keyed by field name, always with every field
 * of fields(): bool for yes/no, int for numbers, the option key for selects, a trimmed string for
 * text, null for an empty or hidden field.
 */
interface EventProposalRequestInterface
{
    /**
     * Stable key, the module name, a dot and a kebab-case name (`event-registrations.registrations`).
     * Stored with every request: never rename it.
     */
    public function key(): string;

    /**
     * Short translated name of what is asked for («Inscripciones»), for lists and headings.
     */
    public function label(): string;

    /**
     * The translated yes/no question that opens the request on the web («¿Quieres inscripciones?»).
     * Unticked, nothing is asked for and nothing is stored.
     */
    public function question(): string;

    /**
     * Position among the requests (lower first; ties by key).
     */
    public function order(): int;

    /**
     * The fields asked for once the question is ticked, in order (may be empty). Read each time:
     * select options may come from the module's records (only the active spaces…).
     *
     * @return list<ProposalRequestFieldDTO>
     */
    public function fields(): array;

    /**
     * Whether people may ask for it now (the module's settings may turn it off). An unavailable
     * request is not offered and, if stored, blocks the approval while it is ticked.
     */
    public function isAvailable(): bool;

    /**
     * Creates the module's configuration for the event from scratch, with the values the reviewer
     * approved (the requested ones when the proposal is published directly). Runs inside the
     * approval transaction: throw when it cannot be done, and also when the event already has the
     * module's configuration (never overwrite nor merge). Anything thrown rolls the whole approval
     * back; throw ProposalRequestRefusedException with a translated message for the reviewer to read.
     *
     * @param  array<string, bool|int|string|null>  $values
     *
     * @throws ProposalRequestRefusedException
     */
    public function apply(EventId $eventId, array $values): void;

    /**
     * One translated, plain-text line saying what the values ask for («Inscripciones con aforo de
     * 20 y lista de espera»), for «Mis propuestas», the panel and the mails. The core escapes it.
     *
     * @param  array<string, bool|int|string|null>  $values
     */
    public function describe(array $values): string;
}
