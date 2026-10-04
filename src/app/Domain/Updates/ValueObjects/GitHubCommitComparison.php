<?php

declare(strict_types=1);

namespace App\Domain\Updates\ValueObjects;

/**
 * How a base commit relates to a head ref on GitHub.
 */
final readonly class GitHubCommitComparison
{
    /**
     * @param  int  $aheadBy  Commits in head that are not in base
     * @param  int  $behindBy  Commits in base that are not in head
     * @param  string  $headSha  Latest commit of head (the base itself when nothing is ahead)
     * @param  array<int, array{sha: string, message: string, date: string|null, url: string}>  $commits  Commits in head not in base, newest first
     */
    public function __construct(
        public int $aheadBy,
        public int $behindBy,
        public string $headSha,
        public array $commits,
    ) {}
}
