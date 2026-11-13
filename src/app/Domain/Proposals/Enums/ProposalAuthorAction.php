<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Enums;

/**
 * What the person who proposed an event or article may do with it from the web.
 */
enum ProposalAuthorAction: string
{
    case Edit = 'edit';
    case Submit = 'submit';
    case Withdraw = 'withdraw';
    case Delete = 'delete';
}
