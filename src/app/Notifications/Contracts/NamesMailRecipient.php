<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

/**
 * A notification that must be mailed to an address other than the account's own (for example
 * the new address of an email change). UserModel::routeNotificationForMail() only honours
 * notifications that implement this contract: a method with the same name elsewhere is ignored.
 */
interface NamesMailRecipient
{
    public function mailRecipient(): string;
}
