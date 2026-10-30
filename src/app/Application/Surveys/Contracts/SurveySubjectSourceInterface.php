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
     */
    public function find(string $subjectId): ?SurveySubjectDTO;

    /**
     * Who took part in the subject: accounts, plus guests whose email address is confirmed.
     * An unknown subject has an empty audience.
     */
    public function audience(string $subjectId): SurveyAudienceDTO;

    /**
     * Whether the user organizes the subject (e.g. runs the table). False for an unknown subject.
     */
    public function canManage(string $userId, string $subjectId): bool;
}
