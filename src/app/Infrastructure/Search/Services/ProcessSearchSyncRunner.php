<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

use App\Application\Search\Services\SearchSyncRunnerInterface;
use App\Infrastructure\Updates\Services\ProcessModulePostUpdateRunner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

final class ProcessSearchSyncRunner implements SearchSyncRunnerInterface
{
    private const int TIMEOUT_SECONDS = 300;

    public function sync(?string $type = null): bool
    {
        $command = [ProcessModulePostUpdateRunner::phpBinary(), base_path('artisan'), 'search:sync', '--no-interaction'];

        if ($type !== null) {
            $command[] = '--type='.$type;
        }

        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run($command);

            if (! $result->successful()) {
                $output = trim($result->errorOutput()) !== '' ? $result->errorOutput() : $result->output();

                Log::warning('[Search] The search documents sync had problems.', [
                    'type' => $type,
                    'error' => trim($output) !== '' ? trim($output) : "exit code {$result->exitCode()}",
                ]);
            }

            return $result->successful();
        } catch (Throwable $e) {
            Log::warning('[Search] Could not run the search documents sync.', ['type' => $type, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
