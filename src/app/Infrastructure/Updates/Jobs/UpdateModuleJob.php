<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Jobs;

use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class UpdateModuleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1; // Only try once - updates should not be retried automatically

    public int $timeout = 600; // 10 minutes

    public function __construct(
        public readonly string $moduleName,
    ) {
    }

    public function handle(ModuleUpdaterInterface $updater): void
    {
        Log::info("Starting update for module: {$this->moduleName}");

        try {
            $result = $updater->update(new ModuleName($this->moduleName));

            if ($result->isSuccess()) {
                Log::info("Module update completed successfully", [
                    'module' => $this->moduleName,
                    'from_version' => $result->fromVersion,
                    'to_version' => $result->toVersion,
                ]);
            } else {
                Log::warning("Module update failed", [
                    'module' => $this->moduleName,
                    'status' => $result->status->value,
                    'error' => $result->errorMessage,
                    'rolled_back' => $result->wasRolledBack(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Module update job failed", [
                'module' => $this->moduleName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['updates', 'module-update', "module:{$this->moduleName}"];
    }

    public function uniqueId(): string
    {
        return "module-update:{$this->moduleName}";
    }
}
