<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Unsubscribe;

use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use Illuminate\Support\Facades\URL;

final readonly class SignedMailUnsubscribeLinks implements MailUnsubscribeLinksInterface
{
    public const string PAGE_ROUTE = 'mail.unsubscribe';

    public const string ONE_CLICK_ROUTE = 'mail.unsubscribe.one-click';

    /**
     * Id of the «Correos» tab of the profile (core#329).
     */
    public const string PREFERENCES_TAB = 'mail';

    public function unsubscribeUrl(string $userId, string $categoryKey): string
    {
        // No expiry: an old mail must still let the person stop that kind of mail
        return URL::signedRoute(self::PAGE_ROUTE, ['user' => $userId, 'category' => $categoryKey]);
    }

    public function oneClickUrl(string $userId, string $categoryKey): string
    {
        return URL::signedRoute(self::ONE_CLICK_ROUTE, ['user' => $userId, 'category' => $categoryKey]);
    }

    public function preferencesUrl(): string
    {
        return route('profile.show', ['tab' => self::PREFERENCES_TAB]);
    }
}
