<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Sources;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\DTOs\EditorTextDTO;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Support\Facades\DB;

/**
 * Texts kept in rows of the settings table, read from the table (the settings cache may be stale) and
 * written through the settings service, which forgets the cached value.
 */
abstract class SettingsEditorTextSource implements EditorTextSourceInterface
{
    public function __construct(
        private readonly SettingsServiceInterface $settings,
    ) {}

    /**
     * @return list<string> the settings keys holding the texts
     */
    abstract protected function keys(): array;

    public function texts(): iterable
    {
        foreach ($this->keys() as $key) {
            $html = $this->stored($key);

            if ($html !== null && $html !== '') {
                yield new EditorTextDTO($key, $html, 'settings#'.$key);
            }
        }
    }

    public function replace(EditorTextDTO $text, string $html): bool
    {
        if (! in_array($text->id, $this->keys(), true) || $this->stored($text->id) !== $text->html) {
            return false;
        }

        $this->settings->set($text->id, $html);

        return true;
    }

    private function stored(string $key): ?string
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return is_string($value) ? $value : null;
    }
}
