<?php

declare(strict_types=1);

namespace App\Console\Commands\Content;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\Services\EditorImageMoverInterface;
use App\Application\Content\Services\EditorTextSourceRegistryInterface;
use App\Console\Concerns\ReadsTypedInput;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

/**
 * Moves the images that the rich editors stored on the local disk before core#481 to Cloudinary
 * (EditorImageMoverInterface): those of the core's texts and of every loaded module's (core#496).
 * Isolated by default: a second run at the same time stops at once.
 */
final class MoveEditorImagesToCloudinaryCommand extends Command implements Isolatable
{
    use ReadsTypedInput;

    protected $signature = 'content:move-editor-images-to-cloudinary
                            {--dry-run : List what would move without uploading, writing or deleting anything}
                            {--host=* : A previous host of the site whose "/storage/" URLs are local images too}
                            {--source= : Only the texts of this source type (a local copy another text names is kept)}';

    protected $description = 'Move the images of the rich text editors from the local disk to Cloudinary';

    /** @var bool */
    protected $isolated = true;

    public function handle(EditorImageMoverInterface $mover, EditorTextSourceRegistryInterface $sources): int
    {
        $hosts = $this->hosts();
        $source = $this->option('source');
        $source = is_string($source) && $source !== '' ? $source : null;

        if ($hosts === null) {
            return self::FAILURE;
        }

        if ($source !== null && ! $sources->source($source) instanceof EditorTextSourceInterface) {
            $types = array_map(static fn (EditorTextSourceInterface $known): string => $known->type(), $sources->sources());
            $this->error("Unknown source: {$source}. Nothing was changed. Sources: ".implode(', ', $types).'.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $mover->move($dryRun, $hosts, $source);

        foreach ($report->moved as $line) {
            $this->line("  {$line}");
        }

        $this->section('Broken references (left as they are):', $report->broken);
        $this->section('On other hosts (left as they are; pass the previous hosts of the site with --host):', $report->otherHosts);
        $this->section('Kept on the local disk (moved, but the local copy stays):', $report->kept);
        $this->section('No local copy is deleted in this run, because:', $report->held);
        $this->section('Not moved (the value and its local copy are kept; run the command again):', $report->failed);

        $verb = $dryRun ? 'would move' : 'moved';
        $this->info(sprintf(
            '%d value(s) checked, %d image(s) %s, %d broken reference(s), %d on other hosts, %d kept locally, %d failure(s).',
            $report->checked,
            count($report->moved),
            $verb,
            count($report->broken),
            count($report->otherHosts),
            count($report->kept),
            count($report->failed),
        ));

        return $report->failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The --host values, trimmed, lowercase and without repeats; null (after saying why) when one is
     * not a host name, so a typo never runs a move with fewer hosts than meant.
     *
     * @return list<string>|null
     */
    private function hosts(): ?array
    {
        $hosts = [];
        foreach ($this->stringListOption('host') as $value) {
            $host = strtolower(trim($value));

            if ($host === '' || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                $this->error("Not a host name: {$value}. Nothing was changed.");

                return null;
            }

            $hosts[] = $host;
        }

        return array_values(array_unique($hosts));
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
