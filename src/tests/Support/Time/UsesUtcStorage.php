<?php

declare(strict_types=1);

namespace Tests\Support\Time;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Time\StorageTimezoneResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait UsesUtcStorage
{
    /** Stores dates in UTC for this test, as a site does once its marker says so. */
    protected function useUtcStorage(): void
    {
        $now = now()->toDateTimeString();

        DB::table('settings')->updateOrInsert(
            ['key' => StorageTimezoneResolver::SETTING_KEY],
            ['id' => (string) Str::uuid(), 'value' => 'UTC', 'created_at' => $now, 'updated_at' => $now],
        );

        (new StorageTimezoneResolver)->apply($this->app);
        app(SettingsServiceInterface::class)->clearCache();
    }
}
