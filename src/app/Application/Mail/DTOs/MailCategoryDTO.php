<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

use Closure;

/**
 * A kind of mail the core or a module sends, which each person can see (and, when it is
 * optional, turn off) in their profile.
 *
 * The section, label, description and reason are translation keys (or plain text): they are
 * translated when the categories are listed, in the locale of that moment.
 */
final readonly class MailCategoryDTO
{
    /**
     * @param  string  $key  "<prefix>.<name>", where the prefix is "core" or the module name.
     * @param  string  $section  Heading the category is listed under; categories with the same one go together.
     * @param  string|null  $mandatoryReason  Why it is always sent; null for an optional category.
     * @param  (Closure(object): bool)|null  $audience  Whether a person sees the category; null shows it to everyone.
     *                                                  It only decides what is listed, never whether a mail goes out.
     */
    private function __construct(
        public string $key,
        public string $section,
        public string $label,
        public string $description,
        public ?string $mandatoryReason,
        public ?Closure $audience,
    ) {}

    /**
     * A category the person may turn off. Without a preference it is sent.
     *
     * @param  (Closure(object): bool)|null  $audience
     */
    public static function optional(
        string $key,
        string $section,
        string $label,
        string $description,
        ?Closure $audience = null,
    ): self {
        return new self($key, $section, $label, $description, null, $audience);
    }

    /**
     * A category that is always sent: it confirms something the person did, changes their
     * plans or asks them to act. The reason is shown next to the locked switch.
     *
     * @param  (Closure(object): bool)|null  $audience
     */
    public static function mandatory(
        string $key,
        string $section,
        string $label,
        string $description,
        string $reason,
        ?Closure $audience = null,
    ): self {
        return new self($key, $section, $label, $description, $reason, $audience);
    }

    public function isMandatory(): bool
    {
        return $this->mandatoryReason !== null;
    }
}
