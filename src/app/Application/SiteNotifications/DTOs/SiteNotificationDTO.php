<?php

declare(strict_types=1);

namespace App\Application\SiteNotifications\DTOs;

use Closure;
use InvalidArgumentException;

/**
 * One notice in the site's notification centre (the bell in the header), as a notification
 * gives it from HasSiteNotification::toSiteNotification().
 *
 * Every text is plain: it is put on one line, cut, and escaped wherever it is shown, so names
 * written by people go in as they are. The url is a path of the site, never another host.
 */
final readonly class SiteNotificationDTO
{
    public const int TITLE_MAX_LENGTH = 160;

    public const int BODY_MAX_LENGTH = 300;

    public const int URL_MAX_LENGTH = 2048;

    // Every pattern ends in \z, not $: PCRE lets $ match before a final newline

    /** Lucide icon name in kebab case, as for mail sections. */
    public const string ICON_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*\z/';

    /** Stored and compared as it is: letters, digits and . _ : - only. */
    public const string GROUP_KEY_PATTERN = '/^[A-Za-z0-9._:-]{1,191}\z/';

    /** An account id, as stored (a UUID). */
    public const string ACTOR_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}\z/';

    public string $title;

    public ?string $body;

    /**
     * @param  string  $title  What happened, in one line («Ana respondió en «La tumba»»).
     * @param  string  $url  Path of the site the notice opens («/foro/temas/la-tumba#m42»).
     * @param  string|null  $body  A short excerpt, plain text.
     * @param  string|null  $icon  Lucide icon name («message-square»); an unknown one shows a bell.
     * @param  string|null  $groupKey  Unread notices with the same key become one row («forum.topic:<id>»).
     * @param  string|null  $actorId  Account that caused it: a notice to that same account is never stored,
     *                                and the group counts distinct actors with it.
     * @param  (Closure(SiteNotificationGroupDTO): string)|null  $groupedTitle  Title of the row once the group
     *                                                                          holds more than one notice.
     *
     * @throws InvalidArgumentException When something could not be stored or would leave the site.
     */
    public function __construct(
        string $title,
        public string $url,
        ?string $body = null,
        public ?string $icon = null,
        public ?string $groupKey = null,
        public ?string $actorId = null,
        public ?Closure $groupedTitle = null,
    ) {
        $normalizedTitle = $this->oneLine($title, self::TITLE_MAX_LENGTH);

        if ($normalizedTitle === '') {
            throw new InvalidArgumentException('The title of a site notification is empty.');
        }

        $this->title = $normalizedTitle;

        $normalizedBody = $body === null ? '' : $this->oneLine($body, self::BODY_MAX_LENGTH);
        $this->body = $normalizedBody === '' ? null : $normalizedBody;

        if (! self::isSitePath($url)) {
            throw new InvalidArgumentException(sprintf('The url of a site notification must be a path of the site, "%s" given.', $url));
        }

        if ($icon !== null && preg_match(self::ICON_PATTERN, $icon) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid site notification icon "%s".', $icon));
        }

        if ($groupKey !== null && preg_match(self::GROUP_KEY_PATTERN, $groupKey) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid site notification group key "%s".', $groupKey));
        }

        if ($actorId !== null && preg_match(self::ACTOR_ID_PATTERN, $actorId) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid site notification actor id "%s".', $actorId));
        }
    }

    /**
     * The title of the row once the group holds this notice: the grouped title when there is
     * more than one notice and it gives a text, this notice's own title otherwise.
     */
    public function titleFor(SiteNotificationGroupDTO $group): string
    {
        if ($group->count < 2 || ! $this->groupedTitle instanceof Closure) {
            return $this->title;
        }

        $grouped = $this->oneLine((string) ($this->groupedTitle)($group), self::TITLE_MAX_LENGTH);

        return $grouped === '' ? $this->title : $grouped;
    }

    /**
     * A path of this site: «/» followed by anything but a second slash or a backslash, with no
     * whitespace or control characters. The same check guards the redirect when a notice is opened.
     */
    public static function isSitePath(string $url): bool
    {
        return strlen($url) <= self::URL_MAX_LENGTH
            && preg_match('#^/(?![/\\\\])[^\s\p{Cc}\\\\]*\z#u', $url) === 1;
    }

    private function oneLine(string $text, int $maxLength): string
    {
        // One line, no control or invisible formatting characters
        $oneLine = preg_replace(['/\s+/u', '/[\p{Cc}\p{Cf}]/u'], [' ', ''], $text);

        if ($oneLine === null) {
            throw new InvalidArgumentException('The text of a site notification is not valid UTF-8.');
        }

        $trimmed = trim($oneLine);

        if (mb_strlen($trimmed) <= $maxLength) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, $maxLength - 1)).'…';
    }
}
