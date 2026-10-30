<?php

declare(strict_types=1);

namespace App\Application\Surveys\Contracts;

use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;

/**
 * Offers a module's records (game tables, tournaments, events…) as survey subjects.
 *
 * Modules return their implementations from ModuleServiceProvider::registerSurveySubjectSources()
 * and dispatch SurveySubjectEnded when a subject ends. Nothing here depends on the surveys
 * module: without it, registered sources are simply never asked.
 *
 * Subjects are never listed or searched: the surveys module learns about one from
 * SurveySubjectEnded or from a link on the module's own page carrying (type, id).
 * User ids are account UUIDs.
 */
interface SurveySubjectSourceInterface
{
    /**
     * Unique, stable kebab-case type of the subjects this source offers (e.g. 'game-table').
     * Surveys store it, so it must never change.
     */
    public function sourceType(): string;

    /**
     * Human-readable, translated label for this kind of subject.
     */
    public function sourceLabel(): string;

    /**
     * Snapshot of the subject, or null when it does not exist or this source does not offer it.
     * The DTO's sourceType must equal sourceType().
     */
    public function find(string $subjectId): ?SurveySubjectDTO;

    /**
     * Who took part in the subject and is asked to answer: accounts, plus guests whose
     * email address is confirmed. Organizers are left out unless they also took part
     * (e.g. a director who also played), so the response rate counts participants only.
     * An unknown subject has an empty audience.
     *
     * @throws \InvalidArgumentException when the source builds an invalid audience (see SurveyAudienceDTO)
     */
    public function audience(string $subjectId): SurveyAudienceDTO;

    /**
     * Whether the user may manage this subject's surveys on the public site: its organizers
     * (e.g. the table's directors) and whoever the module lets act for them. Checked live,
     * unlike SurveySubjectDTO::$organizerIds, a snapshot for statistics. False for an
     * unknown subject.
     */
    public function canManage(string $userId, string $subjectId): bool;
}
