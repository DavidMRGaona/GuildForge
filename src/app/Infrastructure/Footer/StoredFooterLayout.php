<?php

declare(strict_types=1);

namespace App\Infrastructure\Footer;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

/**
 * The footer's settings row as it is in the table, decoded, never through the settings cache (which
 * may be stale). For what must not miss a use of a file: it throws when the row cannot be read, so
 * nothing looks unused because of it.
 */
final readonly class StoredFooterLayout
{
    /**
     * @return array<array-key, mixed>
     */
    public function read(bool $lock = false): array
    {
        $query = DB::table('settings')->where('key', FooterLayoutServiceInterface::SETTING_KEY);
        $value = ($lock ? $query->lockForUpdate() : $query)->value('value');

        if (! is_string($value) || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The stored footer layout is not valid JSON.', $e->getCode(), previous: $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Every block of the stored layout, by column and position.
     *
     * @return iterable<array{column: int, position: int, key: string, settings: array<array-key, mixed>}>
     */
    public function blocks(): iterable
    {
        foreach ($this->read()['columns'] ?? [] as $column => $data) {
            foreach (is_array($data) && is_array($data['blocks'] ?? null) ? $data['blocks'] : [] as $position => $block) {
                if (is_array($block) && is_string($block['key'] ?? null) && is_array($block['settings'] ?? null)) {
                    yield ['column' => (int) $column, 'position' => (int) $position, 'key' => $block['key'], 'settings' => $block['settings']];
                }
            }
        }
    }
}
