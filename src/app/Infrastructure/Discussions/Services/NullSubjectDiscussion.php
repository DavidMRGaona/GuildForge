<?php

declare(strict_types=1);

namespace App\Infrastructure\Discussions\Services;

use App\Application\Discussions\Contracts\SubjectDiscussionInterface;
use App\Application\Discussions\DTOs\SubjectDiscussionDTO;

/**
 * Without a forum module, no page shows discussion activity.
 */
final class NullSubjectDiscussion implements SubjectDiscussionInterface
{
    public function forSubject(string $type, string $id, ?string $userId): ?SubjectDiscussionDTO
    {
        return null;
    }
}
