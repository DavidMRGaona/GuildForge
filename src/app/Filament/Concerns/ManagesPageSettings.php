<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Application\Services\SettingsServiceInterface;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;
use JsonException;

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
        $defaults = $this->getDefaultSettings();

        foreach ($this->getSettingsKeys() as $key) {
            $value = $settingsService->get($key, null);

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

        foreach ($this->getSettingsKeys() as $key) {
            $value = $formData[$key] ?? '';

            // Handle image cleanup before saving
            if (in_array($key, $imageFields, true)) {
                $newValue = (string) $value;
                $this->cleanupOldImage($settingsService, $key, $newValue);
                $settingsService->set($key, $newValue);

                continue;
            }

            // Handle JSON encoding
            if (in_array($key, $jsonFields, true)) {
                $jsonValue = $this->encodeJsonField(is_array($value) ? $value : []);
                $settingsService->set($key, $jsonValue);

                continue;
            }

            // Handle regular string values
            $settingsService->set($key, (string) $value);
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
     * Delete old image from storage if the value has changed.
     */
    protected function cleanupOldImage(
        SettingsServiceInterface $settingsService,
        string $key,
        string $newValue
    ): void {
        $oldValue = (string) $settingsService->get($key, '');

        if ($oldValue !== '' && $oldValue !== $newValue) {
            Storage::disk('images')->delete($oldValue);
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
