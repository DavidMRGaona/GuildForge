<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

/**
 * Who among many accounts is an association member now, in one call (core#824): the batched form of
 * AssociationMembershipServiceInterface::isAssociationMember(), in an interface of its own so the test
 * doubles of that one keep working. It applies the same rule (ActiveRoleResolver: the role counts only
 * while its requirements hold), so what this answers and what the profile page's badge asks never drift apart.
 *
 * Reads the stored roles of the accounts with one query, plus the two-factor reader once if some of those
 * roles need it. It never answers «nobody» for what it could not read: the role setting or a two-factor
 * reader that fails throws (AssociationMembersUnavailableException or the setting's own error).
 */
interface AssociationMembersAmongInterface
{
    /**
     * @param  list<string>  $userIds  account ids; read in lower case, anything that is not an account id ignored
     * @return array<string, true> the ids (lower case) of the members among them
     */
    public function among(array $userIds): array;
}
