<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubCommitComparison;
use App\Infrastructure\Updates\Services\CoreUpdateChecker;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CoreUpdateCheckerTest extends TestCase
{
    private const string DEPLOYED = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private MockInterface&CoreVersionServiceInterface $versionService;

    private MockInterface&GitHubReleaseFetcherInterface $github;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionService = Mockery::mock(CoreVersionServiceInterface::class);
        $this->github = Mockery::mock(GitHubReleaseFetcherInterface::class);
        config(['updates.core.owner' => 'DavidMRGaona', 'updates.core.repo' => 'guildforge', 'updates.core.branch' => 'main']);
    }

    public function test_it_lists_the_commits_on_the_branch_that_are_not_deployed(): void
    {
        $this->versionService->shouldReceive('getCurrentCommit')->andReturn(self::DEPLOYED);
        $this->github->shouldReceive('compareCommits')
            ->with('DavidMRGaona', 'guildforge', self::DEPLOYED, 'main')
            ->andReturn(new GitHubCommitComparison(aheadBy: 1, behindBy: 0, headSha: 'bbb', commits: [
                ['sha' => 'bbb', 'message' => 'fix: one', 'date' => '2026-10-04T10:00:00Z', 'url' => 'https://github.com/o/r/commit/bbb'],
            ]));

        $status = $this->checker()->check();

        $this->assertFalse($status->isUpToDate());
        $this->assertSame(1, $status->behindBy);
        $this->assertSame('main', $status->branch);
        $this->assertSame(self::DEPLOYED, $status->deployedCommit);
        $this->assertSame('bbb', $status->latestCommit);
        $this->assertSame('fix: one', $status->commits[0]['message']);
    }

    public function test_it_is_up_to_date_when_the_branch_has_nothing_new(): void
    {
        $this->versionService->shouldReceive('getCurrentCommit')->andReturn(self::DEPLOYED);
        $this->github->shouldReceive('compareCommits')
            ->andReturn(new GitHubCommitComparison(aheadBy: 0, behindBy: 0, headSha: self::DEPLOYED, commits: []));

        $this->assertTrue($this->checker()->check()->isUpToDate());
    }

    public function test_it_compares_with_the_configured_branch(): void
    {
        config(['updates.core.branch' => 'release']);
        $this->versionService->shouldReceive('getCurrentCommit')->andReturn(self::DEPLOYED);
        $this->github->shouldReceive('compareCommits')
            ->with('DavidMRGaona', 'guildforge', self::DEPLOYED, 'release')
            ->once()
            ->andReturn(new GitHubCommitComparison(0, 0, self::DEPLOYED, []));

        $this->assertSame('release', $this->checker()->check()->branch);
    }

    public function test_it_flags_a_deployed_commit_that_is_not_on_the_branch(): void
    {
        $this->versionService->shouldReceive('getCurrentCommit')->andReturn(self::DEPLOYED);
        $this->github->shouldReceive('compareCommits')
            ->andReturn(new GitHubCommitComparison(aheadBy: 0, behindBy: 3, headSha: 'ccc', commits: []));

        $this->assertTrue($this->checker()->check()->diverged);
    }

    public function test_it_fails_when_the_deployed_commit_is_unknown(): void
    {
        $this->versionService->shouldReceive('getCurrentCommit')->andReturn('unknown');
        $this->github->shouldNotReceive('compareCommits');

        $this->expectException(UpdateException::class);

        $this->checker()->check();
    }

    public function test_it_lets_github_errors_through(): void
    {
        $this->versionService->shouldReceive('getCurrentCommit')->andReturn(self::DEPLOYED);
        $this->github->shouldReceive('compareCommits')
            ->andThrow(UpdateException::githubRequestFailed('DavidMRGaona/guildforge', 'HTTP 403'));

        $this->expectExceptionMessage('HTTP 403');

        $this->checker()->check();
    }

    public function test_service_is_registered_in_container(): void
    {
        $this->assertInstanceOf(CoreUpdateChecker::class, app(CoreUpdateCheckerInterface::class));
    }

    private function checker(): CoreUpdateChecker
    {
        return new CoreUpdateChecker($this->versionService, $this->github);
    }
}
