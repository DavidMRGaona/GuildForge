<?php

declare(strict_types=1);

namespace App\Application\Activity\Services;

use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityDescriptionsResultDTO;
use App\Application\Activity\DTOs\ActivityFactsResultDTO;
use App\Application\Activity\DTOs\ActivityHistoryResultDTO;
use App\Application\Activity\DTOs\ActivitySourceSummaryDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * How a consumer reads activity sources (core#774), instead of calling them: it builds the source
 * for the call, catches anything it throws, checks what it returns and warns once a day per type and
 * reason. It never throws. Every result says whether it was read: «this subject has these facts
 * (maybe none)» is told apart from «the source is missing or failed», so a record kept from it is
 * only replaced by an answer, never emptied by a failure. `Missing` is only for a type no module
 * registers; a registered one that cannot be offered now (two classes with its type, a build that
 * throws) is `Failed`. Facts of anonymized accounts are left out of facts() and history(); those of
 * deactivated accounts stay. Subject ids that are UUIDs are compared in lower case.
 */
interface ActivityReaderInterface
{
    /**
     * The sources offered now, with their labels; one that fails to build or to give its label is left out.
     *
     * @return list<ActivitySourceSummaryDTO>
     */
    public function sources(): array;

    /** An id that cannot be a subject's is read with no facts, without asking the source */
    public function facts(string $type, string $subjectId): ActivityFactsResultDTO;

    /**
     * One page of the source's history. `$limit` is kept within 1 and ActivityHistoryPageDTO::MAX_LIMIT;
     * a page longer than that or whose next cursor is the one asked for is read as failed. Only a
     * page pointing back to itself is caught: a walk keeps the cursors it has seen (or a cap on
     * pages) and stops when one comes back.
     */
    public function history(string $type, ?string $cursor, int $limit): ActivityHistoryResultDTO;

    /**
     * Only subjects asked for, each once; anything else the source returns is dropped, and so is a
     * link that is neither a path of the site nor an https address (the title stays). An answer with
     * nothing well formed in it (a list instead of a map keyed by id) is failed.
     *
     * @param  list<string>  $subjectIds
     */
    public function describe(string $type, array $subjectIds, ?Authenticatable $viewer): ActivityDescriptionsResultDTO;

    /**
     * Empty when the source does not offer attributes, is missing or fails.
     *
     * @return list<ActivityAttributeDTO>
     */
    public function attributes(string $type): array;

    /**
     * Empty when the source does not offer them, is missing or fails.
     *
     * @return list<ActivityAttributeValueDTO>
     */
    public function attributeValues(string $type, string $key): array;
}
