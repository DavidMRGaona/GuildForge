<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Content;

use App\Application\Content\DTOs\EditorImageMoveReportDTO;
use App\Application\Content\Services\EditorImageMoverInterface;
use Mockery;
use Tests\TestCase;

final class MoveEditorImagesToCloudinaryCommandTest extends TestCase
{
    public function test_a_dry_run_lists_what_would_move(): void
    {
        $this->mover(true, new EditorImageMoveReportDTO(4, ['articles.content#a1: /storage/x.png → articles/content/2026/11/u.png'], ['events.description#e1: /storage/missing.png'], []));

        $this->artisanCommand('content:move-editor-images-to-cloudinary', ['--dry-run' => true])
            ->expectsOutput('  articles.content#a1: /storage/x.png → articles/content/2026/11/u.png')
            ->expectsOutput('Broken references (left as they are):')
            ->expectsOutput('  events.description#e1: /storage/missing.png')
            ->expectsOutput('4 value(s) checked, 1 image(s) would move, 1 broken reference(s), 0 on other hosts, 0 failure(s).')
            ->assertExitCode(0);
    }

    public function test_a_run_reports_what_moved(): void
    {
        $this->mover(false, new EditorImageMoveReportDTO(4, ['articles.content#a1: /storage/x.png → articles/content/2026/11/u.png'], [], []));

        $this->artisanCommand('content:move-editor-images-to-cloudinary')
            ->expectsOutput('  articles.content#a1: /storage/x.png → articles/content/2026/11/u.png')
            ->doesntExpectOutput('Broken references (left as they are):')
            ->expectsOutput('4 value(s) checked, 1 image(s) moved, 0 broken reference(s), 0 on other hosts, 0 failure(s).')
            ->assertExitCode(0);
    }

    public function test_a_failure_ends_with_an_error(): void
    {
        $this->mover(false, new EditorImageMoveReportDTO(4, [], [], ['articles.content#a1: /storage/x.png (Upload refused)']));

        $this->artisanCommand('content:move-editor-images-to-cloudinary')
            ->expectsOutput('Not moved (the value and its local copy are kept; run the command again):')
            ->expectsOutput('  articles.content#a1: /storage/x.png (Upload refused)')
            ->expectsOutput('4 value(s) checked, 0 image(s) moved, 0 broken reference(s), 0 on other hosts, 1 failure(s).')
            ->assertExitCode(1);
    }

    public function test_images_on_other_hosts_are_listed(): void
    {
        $this->mover(true, new EditorImageMoveReportDTO(4, [], [], [], ['articles.content#a1: https://old.example/storage/x.png (file found here: pass --host=old.example)']));

        $this->artisanCommand('content:move-editor-images-to-cloudinary', ['--dry-run' => true])
            ->expectsOutput('On other hosts (left as they are; pass the previous hosts of the site with --host):')
            ->expectsOutput('  articles.content#a1: https://old.example/storage/x.png (file found here: pass --host=old.example)')
            ->expectsOutput('4 value(s) checked, 0 image(s) would move, 0 broken reference(s), 1 on other hosts, 0 failure(s).')
            ->assertExitCode(0);
    }

    public function test_the_hosts_passed_reach_the_mover_normalized(): void
    {
        $this->mover(false, new EditorImageMoveReportDTO(4, [], [], []), ['old.example', 'older.example']);

        $this->artisanCommand('content:move-editor-images-to-cloudinary', ['--host' => ['OLD.example', ' older.example ', 'old.example']])
            ->assertExitCode(0);
    }

    public function test_a_value_that_is_not_a_host_changes_nothing(): void
    {
        $mover = Mockery::mock(EditorImageMoverInterface::class);
        $mover->shouldNotReceive('move');
        $this->app->instance(EditorImageMoverInterface::class, $mover);

        $this->artisanCommand('content:move-editor-images-to-cloudinary', ['--host' => ['https://old.example/storage']])
            ->expectsOutput('Not a host name: https://old.example/storage. Nothing was changed.')
            ->assertExitCode(1);
    }

    /**
     * @param  list<string>  $hosts
     */
    private function mover(bool $dryRun, EditorImageMoveReportDTO $report, array $hosts = []): void
    {
        $mover = Mockery::mock(EditorImageMoverInterface::class);
        $mover->shouldReceive('move')->once()->with($dryRun, $hosts)->andReturn($report);
        $this->app->instance(EditorImageMoverInterface::class, $mover);
    }
}
