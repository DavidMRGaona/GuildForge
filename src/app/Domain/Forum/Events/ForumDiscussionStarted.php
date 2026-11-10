<?php

declare(strict_types=1);

namespace App\Domain\Forum\Events;

use InvalidArgumentException;

/**
 * A discussion has been started in the forum linked to the site and its owner asked to announce it.
 *
 * The forum module dispatches it once, only for discussions anyone can see and only for the tags
 * its settings choose. A module that posts to external channels listens to it; without one,
 * nothing happens. It carries what a public announcement shows and nothing personal: no author,
 * no audience.
 *
 * `$discussionId` identifies the discussion in the forum. `$url` is its public address.
 * `$excerpt` is plain text and may be empty. `$tags` are labels of its tags (their names or short names, as the forum module has them).
 */
final readonly class ForumDiscussionStarted
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $discussionId,
        public string $title,
        public string $url,
        public string $excerpt = '',
        public array $tags = [],
    ) {
        if (trim($discussionId) === '') {
            throw new InvalidArgumentException('A forum discussion announcement needs the discussion id.');
        }

        if (trim($title) === '') {
            throw new InvalidArgumentException("Forum discussion '{$discussionId}' needs a title to be announced.");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException("Forum discussion '{$discussionId}' needs an absolute http(s) URL.");
        }
    }
}
