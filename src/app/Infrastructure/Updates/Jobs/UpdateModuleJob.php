<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Jobs;

use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class UpdateModuleJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1; // Only try once - updates should not be retried automatically

    public int $timeout = 600; // 10 minutes

    /** Seconds the uniqueness lock lasts if the job never finishes */
    public int $uniqueFor = 900;

    public function __construct(
        public readonly string $moduleName,
    ) {}

    public function handle(ModuleUpdaterInterface $updater): void
    {
        Log::info("Starting update for module: {$this->moduleName}");

        try {
            $result = $updater->update(new ModuleName($this->moduleName));

            if ($result->isSuccess()) {
                Log::info('Module update completed successfully', [
                    'module' => $this->moduleName,
                    'from_version' => $result->fromVersion,
                    'to_version' => $result->toVersion,
                ]);
            } else {
                Log::warning('Module update failed', [
                    'module' => $this->moduleName,
                    'status' => $result->status->value,
                    'error' => $result->errorMessage,
                    'rolled_back' => $result->wasRolledBack(),
                ]);
            }
        } catch (\Throwable $e) {
            // update() only throws before it records history (lock, module or source missing);
            // record the failure so the admin page following this job can report it
            ModuleUpdateHistoryModel::create([
                'id' => (string) Str::uuid(),
                'module_name' => $this->moduleName,
                'from_version' => 'unknown',
                'to_version' => 'unknown',
                'status' => UpdateStatus::Failed,
                'error_message' => $e->getMessage(),
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            Log::error('Module update job failed', [
                'module' => $this->moduleName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Workers keep the old module classes loaded; make them exit after this job
            Artisan::call('queue:restart');
        }
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return ['updates', 'module-update', "module:{$this->moduleName}"];
    }

    public function uniqueId(): string
    {
        return "module-update:{$this->moduleName}";
    }
}
