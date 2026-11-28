<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Media\Services\MediaSourceSyncState;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The fingerprints the library trusts are written by every sync and dropped by media:forget-source (core#853):
 * both read the stored row, never the settings cache, so neither brings back what the other took away.
 */
final class MediaSourceSyncStateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function state(): MediaSourceSyncState
    {
        return $this->app->make(MediaSourceSyncState::class);
    }

    /**
     * @return array<string, string>
     */
    private function stored(): array
    {
        $value = SettingModel::query()->where('key', MediaSourceSyncState::KEY)->value('value');
        $this->assertIsString($value);

        /** @var array<string, string> */
        return json_decode($value, true, 3, JSON_THROW_ON_ERROR);
    }

    public function test_forgetting_a_fingerprint_drops_that_type_only(): void
    {
        $this->state()->record(['events' => 'a', 'shop.items' => 'b'], []);

        $this->assertTrue($this->state()->forgetFingerprint('shop.items'));

        $this->assertSame(['events' => 'a'], $this->stored());
        $this->assertSame(['events' => 'a'], $this->state()->all());
    }

    public function test_forgetting_a_fingerprint_nobody_recorded_changes_nothing(): void
    {
        $this->assertTrue($this->state()->forgetFingerprint('shop.items'));
        $this->state()->record(['events' => 'a'], []);

        $this->assertTrue($this->state()->forgetFingerprint('shop.items'));

        $this->assertSame(['events' => 'a'], $this->stored());
    }

    public function test_forgetting_a_fingerprint_reads_the_stored_row_not_the_cache(): void
    {
        $this->state()->record(['events' => 'a'], []);
        // Cached by this process
        $this->assertSame(['events' => 'a'], $this->state()->all());
        // Recorded meanwhile by a sync in another process
        SettingModel::query()->where('key', MediaSourceSyncState::KEY)->update(['value' => '{"events":"a","shop.items":"b"}']);

        $this->assertTrue($this->state()->forgetFingerprint('events'));

        $this->assertSame(['shop.items' => 'b'], $this->stored());
    }

    public function test_an_unreadable_state_is_left_alone_and_trusts_nothing(): void
    {
        SettingModel::query()->updateOrCreate(['key' => MediaSourceSyncState::KEY], ['value' => '{not json']);
        app(SettingsServiceInterface::class)->clearCache();

        $this->assertTrue($this->state()->forgetFingerprint('events'));

        $this->assertSame('{not json', SettingModel::query()->where('key', MediaSourceSyncState::KEY)->value('value'));
        $this->assertSame([], $this->state()->all());
    }

    public function test_a_fingerprint_that_cannot_be_forgotten_is_reported_and_logged(): void
    {
        $log = Log::spy();
        Schema::drop('settings');

        $this->assertFalse($this->state()->forgetFingerprint('events'));

        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'fingerprint') && $context['type'] === 'events',
        )->once();
    }

    public function test_a_sync_never_brings_back_a_fingerprint_forgotten_by_another_process(): void
    {
        $this->state()->record(['events' => 'a', 'shop.items' => 'b'], []);
        // Cached by the sync's process before it records
        $this->assertSame(['events' => 'a', 'shop.items' => 'b'], $this->state()->all());
        // media:forget-source in another process
        SettingModel::query()->where('key', MediaSourceSyncState::KEY)->update(['value' => '{"events":"a"}']);

        $this->state()->record(['galleries' => 'c'], []);

        $this->assertSame(['events' => 'a', 'galleries' => 'c'], $this->stored());
    }

    public function test_forgetting_a_refresh_failure_drops_that_type_only(): void
    {
        $this->state()->markRefreshFailed('events');
        $this->state()->markRefreshFailed('shop.items');

        $this->assertTrue($this->state()->forgetRefreshFailure('shop.items'));

        $this->assertSame(['events'], array_keys($this->state()->refreshFailures() ?? []));
    }

    public function test_a_refresh_failure_that_cannot_be_forgotten_is_reported_and_logged(): void
    {
        $log = Log::spy();
        Schema::drop('media_source_refresh_failures');

        $this->assertFalse($this->state()->forgetRefreshFailure('events'));

        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'refresh failure') && $context['type'] === 'events',
        )->once();
    }
}
