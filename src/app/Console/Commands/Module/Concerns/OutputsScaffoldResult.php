<?php

declare(strict_types=1);

namespace App\Console\Commands\Module\Concerns;

use App\Application\Modules\DTOs\ScaffoldResultDTO;

/**
 * Prints what a module:make* command generated and what is still left to do.
 */
trait OutputsScaffoldResult
{
    private function outputResult(ScaffoldResultDTO $result): int
    {
        if ($result->isFailure()) {
            $this->error($result->message);
            foreach ($result->errors as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        foreach ($result->files as $file => $status) {
            $statusLabel = match ($status) {
                ScaffoldResultDTO::STATUS_CREATED => '<fg=green>[CREATED]</>',
                ScaffoldResultDTO::STATUS_SKIPPED => '<fg=yellow>[SKIPPED]</>',
                ScaffoldResultDTO::STATUS_OVERWRITTEN => '<fg=blue>[OVERWRITTEN]</>',
                ScaffoldResultDTO::STATUS_FAILED => '<fg=red>[FAILED]</>',
                default => "<fg=gray>[{$status}]</>",
            };

            $this->line("  {$statusLabel} {$file}");
        }

        foreach ($result->warnings as $warning) {
            $this->warn("  Warning: {$warning}");
        }

        $this->newLine();
        $this->info($result->message);

        if ($result->nextSteps !== []) {
            $this->newLine();
            $this->line('Next steps:');
            foreach ($result->nextSteps as $step) {
                $this->line("  - {$step}");
            }
        }

        return self::SUCCESS;
    }
}
