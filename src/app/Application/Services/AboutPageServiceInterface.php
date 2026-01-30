<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\AboutPageResponseDTO;
use App\Application\DTOs\Response\ActivityDTO;
use App\Application\DTOs\Response\JoinStepDTO;

interface AboutPageServiceInterface
{
    /**
     * Get all data needed for the About page.
     */
    public function getAboutPageData(): AboutPageResponseDTO;

    /**
     * Parse activities JSON string into array of ActivityDTOs.
     *
     * @return array<ActivityDTO>
     */
    public function parseActivities(mixed $json): array;

    /**
     * Parse join steps JSON string into array of JoinStepDTOs.
     *
     * @return array<JoinStepDTO>
     */
    public function parseJoinSteps(mixed $json): array;

    /**
     * Format social URL by prepending https:// if needed.
     */
    public function formatSocialUrl(mixed $url): string;
}
