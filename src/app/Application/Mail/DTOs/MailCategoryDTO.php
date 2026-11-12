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
    /** Shape of an icon name: a Lucide icon in kebab case («calendar-check»). Ends in \z, not $: PCRE lets $ match before a final newline. */
    public const string ICON_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*\z/';

    /**
     * @param  string  $key  "<prefix>.<name>", where the prefix is "core" or the module name.
     * @param  string  $section  Heading the category is listed under; categories with the same one go together.
     * @param  string|null  $mandatoryReason  Why it is always sent; null for an optional category.
     * @param  (Closure(object): bool)|null  $audience  Whether a person sees the category; null shows it to everyone.
     *                                                  It only decides what is listed, never whether a mail goes out.
     * @param  string|null  $icon  Lucide icon name in kebab case («dices») for the section the category is listed under;
     *                             the first category of a section that declares one decides. The profile falls back to a
     *                             generic mail icon for a missing or unknown name.
     * @param  bool  $digestible  Whether a person may get it as a daily digest instead (core#333). Never mandatory.
     * @param  bool  $mail  Whether it is sent by mail (every category before core#491 is).
     * @param  bool  $site  Whether it also appears in the site's notification centre, the bell (core#491).
     */
    private function __construct(
        public string $key,
        public string $section,
        public string $label,
        public string $description,
        public ?string $mandatoryReason,
        public ?Closure $audience,
        public ?string $icon = null,
        public bool $digestible = false,
        public bool $mail = true,
        public bool $site = false,
    ) {}

    /**
     * A category the person may turn off. Without a preference it is sent.
     *
     * A digestible one offers three choices instead of a switch: at once, a daily digest or not
     * at all. Mark as digestible the notices that come in bursts and can wait a day (someone
     * joined, someone left), never those that ask to act soon.
     *
     * With `site: true` it also appears in the notification centre (core#491), with its own
     * switch; `mail: false` keeps it out of mail altogether («me gusta»).
     *
     * @param  (Closure(object): bool)|null  $audience
     */
    public static function optional(
        string $key,
        string $section,
        string $label,
        string $description,
        ?Closure $audience = null,
        ?string $icon = null,
        bool $digestible = false,
        bool $site = false,
        bool $mail = true,
    ): self {
        return new self($key, $section, $label, $description, null, $audience, $icon, $digestible, $mail, $site);
    }

    /**
     * A category that is always sent: it confirms something the person did, changes their
     * plans or asks them to act. The reason is shown next to the locked switch. With `site: true`
     * its notices in the notification centre are always shown too (core#491).
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
        ?string $icon = null,
        bool $site = false,
        bool $mail = true,
    ): self {
        return new self($key, $section, $label, $description, $reason, $audience, $icon, false, $mail, $site);
    }

    public function isMandatory(): bool
    {
        return $this->mandatoryReason !== null;
    }
}
