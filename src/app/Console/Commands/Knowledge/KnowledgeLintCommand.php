<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Infrastructure\Knowledge\Lint\KnowledgeLinter;
use Illuminate\Console\Command;

final class KnowledgeLintCommand extends Command
{
    protected $signature = 'knowledge:lint {--module= : Also check the guides of this module}';

    protected $description = 'Validate the knowledge base guides of the core and, optionally, one module';

    public function handle(KnowledgeLinter $linter): int
    {
        $module = $this->option('module');
        $issues = $linter->lint(is_string($module) && $module !== '' ? $module : null);

        if ($issues === []) {
            $this->info('No issues found.');

            return self::SUCCESS;
        }

        $base = base_path().'/';
        $this->table(
            ['File', 'Issue'],
            array_map(static fn ($issue): array => [str_replace($base, '', $issue->file), $issue->message], $issues),
        );
        $this->error(count($issues).' issue(s) found.');

        return self::FAILURE;
    }
}
