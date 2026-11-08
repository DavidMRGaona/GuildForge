<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use Illuminate\Foundation\Console\ViewCacheCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * view:cache without its view:clear: every Blade template is compiled again over its
 * current compiled file.
 *
 * Blade writes each compiled view to a temporary file and renames it (Filesystem::replace()),
 * so a request always finds a whole file. view:clear first deletes them all, and a request
 * that already checked a compiled view then fails to include it (core#408). Compiled files
 * of templates that no longer exist stay behind; nothing loads them.
 */
#[AsCommand(name: 'view:recompile')]
final class RecompileViewsCommand extends ViewCacheCommand
{
    protected $signature = 'view:recompile';

    protected $description = 'Compile every Blade template again over the current compiled views (used by the updater)';

    protected $hidden = true;

    public function handle(): void
    {
        $this->paths()->each(function (string $path): void {
            $this->compileViews($this->bladeFilesIn([$path]));
        });

        $this->components->info('Blade templates recompiled successfully.');
    }
}
