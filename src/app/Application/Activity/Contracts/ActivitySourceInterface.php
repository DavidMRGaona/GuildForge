<?php

declare(strict_types=1);

namespace App\Application\Activity\Contracts;

use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Domain\Activity\ActivityKind;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Tells other modules who took part in a module's things, and how (core#774): who played or ran a
 * table, who organized or won a tournament, who attended an event. The achievements module keeps a
 * record of it without knowing any module; nothing here depends on it.
 *
 * Modules return their implementations from ModuleServiceProvider::registerActivitySources() and call
 * ActivityPublisherInterface::recorded() wherever a subject's facts may have changed (it ended,
 * somebody joined or left, it was hidden or deleted). Sources are built from the container in each
 * request or job that reads them; consumers read them through ActivityReaderInterface, never directly.
 *
 * Privacy: a source only reports what any signed-in member can know exists (published, non-private
 * tables, posts in categories members see, non-anonymous surveys…). Something restricted is never
 * reported, so a badge or a count built on it never reveals it.
 *
 * facts() and history() answer the same for everybody: they never read the signed-in user, the
 * session or the request. They run in queued jobs and commands, where nobody is signed in; a query
 * scoped to «what the current user may see» would answer nothing there, and the record kept from it
 * would lose real facts.
 */
interface ActivitySourceInterface
{
    /**
     * `<module>.<thing>` (`game-tables.session`), starting with the module that registers it
     * (`core.` for the core). Consumers store it with every fact, so it never changes.
     */
    public function type(): string;

    /** What its subjects are; the same for all of them */
    public function kind(): ActivityKind;

    /** Translated label for this kind of subject («Partidas») */
    public function label(): string;

    /**
     * The current truth of one subject: one fact per person and role. Empty when it does not exist,
     * no longer counts (hidden, deleted, restricted) or has not happened yet. Never throw for an
     * unknown id, and never read who is signed in.
     *
     * @return list<ActivityFactDTO>
     */
    public function facts(string $subjectId): array;

    /**
     * Every subject that counts, with its facts, in a stable order, `$limit` at most per page: for
     * recalculating from scratch. `$cursor` is null for the first page and then the page's
     * nextCursor, opaque to the caller. Throw on a cursor it cannot read (never start again from the
     * first page), and never read who is signed in.
     */
    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO;

    /**
     * Title and link of the subjects asked for that `$viewer` may see (null: a guest), keyed by
     * subject id; leave out the others. Used to show a person their own progress and in the panel.
     *
     * @param  list<string>  $subjectIds
     * @return array<string, ActivitySubjectDTO>
     */
    public function describe(array $subjectIds, ?Authenticatable $viewer): array;
}
