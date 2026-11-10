<?php

declare(strict_types=1);

namespace App\Console\Commands\Content;

use App\Application\Content\Services\EditorImageMoverInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

/**
 * Moves the images that the rich editors stored on the local disk before core#481 to Cloudinary
 * (EditorImageMoverInterface). Isolated by default: a second run at the same time stops at once.
 */
final class MoveEditorImagesToCloudinaryCommand extends Command implements Isolatable
{
    protected $signature = 'content:move-editor-images-to-cloudinary
                            {--dry-run : List what would move without uploading, writing or deleting anything}';

    protected $description = 'Move the images of the rich text editors from the local disk to Cloudinary';

    /** @var bool */
    protected $isolated = true;

    public function handle(EditorImageMoverInterface $mover): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = $mover->move($dryRun);

        foreach ($report->moved as $line) {
            $this->line("  {$line}");
        }

        $this->section('Broken references (left as they are):', $report->broken);
        $this->section('Not moved (the value and its local copy are kept; run the command again):', $report->failed);

        $verb = $dryRun ? 'would move' : 'moved';
        $this->info(sprintf(
            '%d value(s) checked, %d image(s) %s, %d broken reference(s), %d failure(s).',
            $report->checked,
            count($report->moved),
            $verb,
            count($report->broken),
            count($report->failed),
        ));

        return $report->failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $lines
     */
    private function section(string $title, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->warn($title);

        foreach ($lines as $line) {
            $this->line("  {$line}");
        }
    }
}
