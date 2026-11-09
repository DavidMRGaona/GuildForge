<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

/**
 * The addresses a mail to an account carries so the person can stop one kind of mail or choose
 * what they receive.
 *
 * The unsubscribe addresses are signed for one account and one category, never expire and give
 * access to nothing else: changing the account or the category breaks the signature.
 */
interface MailUnsubscribeLinksInterface
{
    /**
     * The page that asks to confirm (opening it never unsubscribes) and offers to undo.
     */
    public function unsubscribeUrl(string $userId, string $categoryKey): string;

    /**
     * The RFC 8058 address of the List-Unsubscribe header: a POST unsubscribes at once.
     */
    public function oneClickUrl(string $userId, string $categoryKey): string;

    /**
     * The «Correos» tab of the profile.
     */
    public function preferencesUrl(): string;
}
