<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

/**
 * What the footer of a mail to an account says about the mail the person receives.
 *
 * - optional: the category can be turned off, through the unsubscribe page or, from the mail
 *   client, the one-click address of the List-Unsubscribe header;
 * - mandatory: the mail is always sent;
 * - without a category (a module not adapted yet): only where to choose.
 *
 * An optional mail about one thing (HasMailSubject) also offers to mute that thing (core#334).
 */
final readonly class MailFooterDTO
{
    private function __construct(
        public string $preferencesUrl,
        public bool $mandatory,
        public ?string $categoryLabel,
        public ?string $unsubscribeUrl,
        public ?string $oneClickUrl,
        public ?string $subjectLabel = null,
        public ?string $muteSubjectUrl = null,
    ) {}

    public static function optional(string $categoryLabel, string $unsubscribeUrl, string $oneClickUrl, string $preferencesUrl): self
    {
        return new self($preferencesUrl, false, $categoryLabel, $unsubscribeUrl, $oneClickUrl);
    }

    public static function mandatory(string $preferencesUrl): self
    {
        return new self($preferencesUrl, true, null, null, null);
    }

    public static function withoutCategory(string $preferencesUrl): self
    {
        return new self($preferencesUrl, false, null, null, null);
    }

    /**
     * The same footer, plus the line to stop the notices of one thing. Only an optional mail
     * offers it: a mandatory one is always sent.
     */
    public function withSubject(string $subjectLabel, string $muteSubjectUrl): self
    {
        if (! $this->isOptional()) {
            return $this;
        }

        return new self(
            $this->preferencesUrl,
            $this->mandatory,
            $this->categoryLabel,
            $this->unsubscribeUrl,
            $this->oneClickUrl,
            $subjectLabel,
            $muteSubjectUrl,
        );
    }

    public function offersToMuteSubject(): bool
    {
        return $this->muteSubjectUrl !== null;
    }

    public function isOptional(): bool
    {
        return $this->unsubscribeUrl !== null;
    }
}
