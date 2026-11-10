<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Media\Sources\RichTextMediaSource;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Shared functionality for Filament settings pages.
 *
 * Pages using this trait should:
 * - Implement getSettingsKeys() to return settings keys to load
 * - Implement getJsonFields() to return field names that need JSON encoding/decoding
 * - Implement getImageFields() to return field names that are image uploads
 * - Use loadSettings() in mount() to load settings
 * - Use saveSettings() in save() to persist settings
 * - Use getFormActions() to get the save button action
 */
trait ManagesPageSettings
{
    /**
     * Get the list of setting keys this page manages.
     *
     * @return array<string>
     */
    abstract protected function getSettingsKeys(): array;

    /**
     * Get the list of field names that need JSON encoding/decoding.
     *
     * These are typically used for Repeater fields.
     *
     * @return array<string>
     */
    abstract protected function getJsonFields(): array;

    /**
     * Get the list of field names that are image uploads.
     *
     * These need cleanup when changed (delete old image from storage).
     *
     * @return array<string>
     */
    abstract protected function getImageFields(): array;

    /**
     * Get the list of field names that should be encrypted at rest.
     *
     * @return array<string>
     */
    abstract protected function getEncryptedFields(): array;

    /**
     * Get the default values for settings.
     *
     * When a setting has not been saved yet, the default value will be used
     * instead of an empty string. This ensures forms show the intended defaults.
     *
     * @return array<string, mixed>
     */
    abstract protected function getDefaultSettings(): array;

    /**
     * Load settings from the service and decode JSON fields.
     *
     * Uses defaults from getDefaultSettings() when a setting has not been saved.
     *
     * @return array<string, mixed>
     */
    protected function loadSettings(SettingsServiceInterface $settingsService): array
    {
        $data = [];
        $jsonFields = $this->getJsonFields();
        $encryptedFields = $this->getEncryptedFields();
        $defaults = $this->getDefaultSettings();

        foreach ($this->getSettingsKeys() as $key) {
            // Encrypted fields use getEncrypted() for transparent decryption
            if (in_array($key, $encryptedFields, true)) {
                $value = $settingsService->getEncrypted($key, $defaults[$key] ?? '');
                $data[$key] = (string) $value;

                continue;
            }

            $value = $settingsService->get($key);

            // If setting has not been saved, use default
            if ($value === null) {
                $data[$key] = $defaults[$key] ?? (in_array($key, $jsonFields, true) ? [] : '');

                continue;
            }

            if (in_array($key, $jsonFields, true)) {
                $data[$key] = $this->decodeJsonField((string) $value);
            } else {
                $data[$key] = (string) $value;
            }
        }

        return $data;
    }

    /**
     * Save settings to the service, encode JSON fields, and handle image cleanup.
     *
     * @param  array<string, mixed>  $formData
     */
    protected function saveSettings(SettingsServiceInterface $settingsService, array $formData): void
    {
        $jsonFields = $this->getJsonFields();
        $imageFields = $this->getImageFields();
        $encryptedFields = $this->getEncryptedFields();
        $savedImageKeys = [];
        /** @var list<string> $replacedImages */
        $replacedImages = [];

        foreach ($this->getSettingsKeys() as $key) {
            $value = $formData[$key] ?? '';

            // A replaced image is released once every key is saved: it may have moved to another key
            if (in_array($key, $imageFields, true)) {
                $newValue = (string) $value;
                $oldValue = (string) $settingsService->get($key, '');

                if ($oldValue !== '' && $oldValue !== $newValue) {
                    $replacedImages[] = $oldValue;
                }

                $settingsService->set($key, $newValue);
                $savedImageKeys[] = $key;

                continue;
            }

            // Handle JSON encoding
            if (in_array($key, $jsonFields, true)) {
                $jsonValue = $this->encodeJsonField(is_array($value) ? $value : []);
                $settingsService->set($key, $jsonValue);

                continue;
            }

            // Handle encrypted fields
            if (in_array($key, $encryptedFields, true)) {
                $stringValue = (string) $value;
                if ($stringValue !== '') {
                    $settingsService->setEncrypted($key, $stringValue);
                } else {
                    $settingsService->set($key, '');
                }

                continue;
            }

            // Handle regular string values
            $settingsService->set($key, (string) $value);
        }

        // Deleted only if nobody uses it any more, these settings included
        foreach (array_unique($replacedImages) as $path) {
            resolve(MediaFileReleaserInterface::class)->release($path);
        }

        // The media library tracks each image setting as a record of the settings source, keyed by its name,
        // and each rich text setting as a record of the rich text source
        $textKeys = array_values(array_intersect($this->getSettingsKeys(), array_keys(RichTextMediaSource::SETTING_PAGES)));
        $this->refreshMediaUsages([
            ...array_map(static fn (string $key): array => ['settings', $key], $savedImageKeys),
            ...array_map(static fn (string $key): array => [RichTextMediaSource::TYPE, 'settings:'.$key], $textKeys),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $records  type and record id of each
     */
    private function refreshMediaUsages(array $records): void
    {
        if ($records === []) {
            return;
        }

        // Never fails the save: the nightly sync settles what was not refreshed
        try {
            $indexer = resolve(MediaIndexerInterface::class);

            foreach ($records as [$type, $recordId]) {
                $indexer->refresh($type, $recordId);
            }
        } catch (Throwable $e) {
            Log::warning('[Media] Could not refresh the media usages of the saved settings.', [
                'records' => $records,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Safely decode a JSON string to an array.
     *
     * Returns an empty array if the value is empty or invalid JSON.
     *
     * @return array<int|string, mixed>
     */
    protected function decodeJsonField(string $value): array
    {
        if ($value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Safely encode an array to a JSON string.
     *
     * Returns an empty string if the array is empty.
     *
     * @param  array<int|string, mixed>  $data
     */
    protected function encodeJsonField(array $data): string
    {
        if (count($data) === 0) {
            return '';
        }

        try {
            return json_encode(array_values($data), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }
    }

    /**
     * The image a setting holds now, which its field always accepts back when saving (a settings page has no
     * record, and the file may not be in the media library yet).
     *
     * @return list<string>
     */
    protected static function storedImagePaths(string $key): array
    {
        $value = (string) resolve(SettingsServiceInterface::class)->get($key, '');

        return $value !== '' ? [$value] : [];
    }

    /**
     * Release the old image if the value has changed: it is deleted later, only if nobody uses it.
     *
     * @deprecated saveSettings() releases replaced images itself, after every key is saved
     */
    protected function cleanupOldImage(
        SettingsServiceInterface $settingsService,
        string $key,
        string $newValue
    ): void {
        $oldValue = (string) $settingsService->get($key, '');

        if ($oldValue !== '' && $oldValue !== $newValue) {
            resolve(MediaFileReleaserInterface::class)->release($oldValue);
        }
    }

    /**
     * Get the form actions (save button).
     *
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }
}
