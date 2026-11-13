<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Enums;

/**
 * Who may propose a type of content from the web. Every level needs an active account with a
 * verified email.
 */
enum ProposalAccessLevel: string
{
    /** Any account */
    case Registered = 'registered';

    /** Holders of one of the chosen roles */
    case Roles = 'roles';

    /** Holders of the type's propose permission */
    case Permission = 'permission';
}
