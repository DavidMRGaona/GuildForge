<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

/**
 * Deployed core commit compared with the branch deployments are built from.
 */
final readonly class CoreUpdateStatusDTO
{
    /**
     * @param  int  $behindBy  Commits on the branch that are not deployed
     * @param  array<int, array{sha: string, message: string, date: string|null, url: string}>  $commits  Those commits, newest first
     * @param  bool  $diverged  The deployed commit is not on the branch (deployed from elsewhere)
     */
    public function __construct(
        public string $deployedCommit,
        public string $branch,
        public int $behindBy,
        public string $latestCommit,
        public array $commits,
        public bool $diverged,
    ) {}

    public function isUpToDate(): bool
    {
        return $this->behindBy === 0;
    }

    /**
     * @return array{deployed_commit: string, branch: string, behind_by: int, latest_commit: string, commits: array<int, array{sha: string, message: string, date: string|null, url: string}>, diverged: bool}
     */
    public function toArray(): array
    {
        return [
            'deployed_commit' => $this->deployedCommit,
            'branch' => $this->branch,
            'behind_by' => $this->behindBy,
            'latest_commit' => $this->latestCommit,
            'commits' => $this->commits,
            'diverged' => $this->diverged,
        ];
    }
}
