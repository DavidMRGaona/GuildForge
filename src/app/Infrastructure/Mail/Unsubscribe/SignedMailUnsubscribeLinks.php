<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Unsubscribe;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use Illuminate\Support\Facades\URL;

final readonly class SignedMailUnsubscribeLinks implements MailUnsubscribeLinksInterface
{
    public const string PAGE_ROUTE = 'mail.unsubscribe';

    public const string ONE_CLICK_ROUTE = 'mail.unsubscribe.one-click';

    public const string MUTE_SUBJECT_ROUTE = 'mail.subject-mute';

    /** Query parameters of the mute link: the names to show and store, covered by the signature. */
    public const string LABEL_PARAMETER = 'nombre';

    public const string TYPE_LABEL_PARAMETER = 'tipo';

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

    public function muteSubjectUrl(string $userId, MailSubjectDTO $subject): string
    {
        // The names travel signed, so the page shows them without asking the module, and changing
        // any of them breaks the signature
        return URL::signedRoute(self::MUTE_SUBJECT_ROUTE, array_filter([
            'user' => $userId,
            'type' => $subject->type,
            'subject' => $subject->id,
            self::LABEL_PARAMETER => $subject->label,
            self::TYPE_LABEL_PARAMETER => $subject->typeLabel,
        ], static fn (?string $value): bool => $value !== null));
    }

    public function preferencesUrl(): string
    {
        return route('profile.show', ['tab' => self::PREFERENCES_TAB]);
    }
}
