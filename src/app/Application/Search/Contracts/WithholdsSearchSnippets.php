<?php

declare(strict_types=1);

namespace App\Application\Search\Contracts;

/**
 * An indexed search source that can tell when the stored text of a result that is still visible no longer holds.
 *
 * The stored documents are refreshed by a queued job, so for a moment after a write a result may still be shown
 * with text that has just been hidden (a moderated opening post, a removed passage) while the result itself stays
 * visible. The site search asks the source of every type that implements this, once per page with every id of that
 * type still shown, and shows those results with their title and no snippet. It fails closed: when this throws, no
 * result of the type shows a snippet. Independent from ChecksSearchHits, which takes whole results away.
 */
interface WithholdsSearchSnippets
{
    /**
     * The ids whose stored text must not be shown, read in one query: never one query per id.
     * Ids not asked for are ignored.
     *
     * @param  non-empty-list<string>  $ids  ids given by document() and documents(), of this source's type
     * @param  object|null  $user  the signed-in person, or null for a guest
     * @return list<string>
     */
    public function staleSearchSnippets(array $ids, ?object $user): array;
}
