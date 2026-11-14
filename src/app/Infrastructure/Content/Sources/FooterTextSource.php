<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Sources;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\DTOs\EditorTextDTO;
use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Content\ValueObjects\EditorImageFolder;
use App\Infrastructure\Footer\SettingsFooterLayoutService;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

/**
 * The text blocks of the configurable footer (core#697). They all live in one settings row, a JSON
 * document, so SettingsEditorTextSource (which writes a whole row as one text) would break it: each
 * text is the `content` of one `core.text` block, identified by its column and position
 * (`<column>.<block>`), and replacing it rewrites only that string, leaving the rest of the row as it
 * was. Read from the table, never the settings cache, which may be stale.
 */
final readonly class FooterTextSource implements EditorTextSourceInterface
{
    /** The text block and the setting holding its HTML (TextFooterBlock) */
    public const string BLOCK_KEY = 'core.text';

    public const string CONTENT_FIELD = 'content';

    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    public function type(): string
    {
        return 'footer';
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageOrigin::Footer->folder();
    }

    public function texts(): iterable
    {
        $layout = $this->stored();

        foreach ($layout['columns'] ?? [] as $column => $data) {
            foreach (is_array($data) && is_array($data['blocks'] ?? null) ? $data['blocks'] : [] as $position => $block) {
                $html = $this->textOf($block);

                if ($html !== null && $html !== '') {
                    $id = $column.'.'.$position;

                    yield new EditorTextDTO($id, $html, 'footer#'.$id);
                }
            }
        }
    }

    public function replace(EditorTextDTO $text, string $html): bool
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $text->id, $matches) !== 1) {
            return false;
        }

        $column = (int) $matches[1];
        $position = (int) $matches[2];

        // Read and written under a row lock: a save from the panel meanwhile is never overwritten
        $written = DB::transaction(function () use ($text, $html, $column, $position): bool {
            $layout = $this->stored(lock: true);

            if (self::textOf($layout['columns'][$column]['blocks'][$position] ?? null) !== $text->html) {
                return false;
            }

            $layout['columns'][$column]['blocks'][$position]['settings'][self::CONTENT_FIELD] = $html;

            try {
                $encoded = SettingsFooterLayoutService::encode($layout);
            } catch (JsonException $e) {
                throw new RuntimeException('The footer layout could not be written back.', $e->getCode(), previous: $e);
            }

            // Moving an image is not an edit: no model events and no updated_at
            DB::table('settings')
                ->where('key', FooterLayoutServiceInterface::SETTING_KEY)
                ->update(['value' => $encoded]);

            return true;
        });

        // Once committed, so nobody caches the old row again
        if ($written) {
            $this->settings->clearCache();
        }

        return $written;
    }

    /**
     * The stored row as it is, decoded. Throws when it cannot be read, so nothing looks unused because of it.
     *
     * @return array<array-key, mixed>
     */
    private function stored(bool $lock = false): array
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

    private function textOf(mixed $block): ?string
    {
        if (! is_array($block) || ($block['key'] ?? null) !== self::BLOCK_KEY || ! is_array($block['settings'] ?? null)) {
            return null;
        }

        $html = $block['settings'][self::CONTENT_FIELD] ?? null;

        return is_string($html) ? $html : null;
    }
}
