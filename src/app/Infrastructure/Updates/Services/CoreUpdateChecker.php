<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\DTOs\CoreUpdateStatusDTO;
use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Updates\Exceptions\UpdateException;

final readonly class CoreUpdateChecker implements CoreUpdateCheckerInterface
{
    public function __construct(
        private CoreVersionServiceInterface $versionService,
        private GitHubReleaseFetcherInterface $github,
    ) {}

    public function check(): CoreUpdateStatusDTO
    {
        $deployedCommit = $this->versionService->getCurrentCommit();

        if ($deployedCommit === 'unknown') {
            throw UpdateException::unknownDeployedCommit();
        }

        $branch = (string) config('updates.core.branch', 'main');
        $comparison = $this->github->compareCommits(
            (string) config('updates.core.owner'),
            (string) config('updates.core.repo'),
            $deployedCommit,
            $branch,
        );

        return new CoreUpdateStatusDTO(
            deployedCommit: $deployedCommit,
            branch: $branch,
            behindBy: $comparison->aheadBy,
            latestCommit: $comparison->headSha,
            commits: $comparison->commits,
            diverged: $comparison->behindBy > 0,
        );
    }
}
