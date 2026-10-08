<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\DTOs\TrixContentMigrationReportDTO;
use App\Application\Content\Services\TrixContentMigratorInterface;
use App\Application\Content\Services\TrixHtmlConverterInterface;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

final readonly class TrixContentMigrator implements TrixContentMigratorInterface
{
    /** Columns edited with the panel's rich editor, by table */
    private const array COLUMNS = [
        'articles' => 'content',
        'events' => 'description',
    ];

    /** Settings edited with the panel's rich editor */
    private const array SETTINGS = [
        'about_history',
        'legal_privacy_content',
        'legal_notice_content',
        'legal_cookies_content',
        'legal_terms_content',
    ];

    public function __construct(
        private TrixHtmlConverterInterface $converter,
        private SettingsServiceInterface $settings,
        private string $backupDirectory,
    ) {
    }

    public function migrate(bool $dryRun = false): TrixContentMigrationReportDTO
    {
        $checked = 0;
        $changes = [];

        foreach (self::COLUMNS as $table => $column) {
            foreach (DB::table($table)->whereNotNull($column)->orderBy('id')->get(['id', $column]) as $row) {
                $checked++;
                $original = (string) $row->{$column};
                $converted = $this->converter->convert($original);

                if ($converted !== $original) {
                    $changes[] = ['table' => $table, 'column' => $column, 'id' => (string) $row->id, 'original' => $original, 'converted' => $converted];
                }
            }
        }

        foreach (self::SETTINGS as $key) {
            // From the table, not the settings cache: a migration must not cache settings that
            // seeders write next, and a stale cache must never be converted over the stored value
            $original = $this->read('settings', 'value', $key);

            if ($original === null) {
                continue;
            }

            $checked++;
            $converted = $this->converter->convert($original);

            if ($converted !== $original) {
                $changes[] = ['table' => 'settings', 'column' => 'value', 'id' => $key, 'original' => $original, 'converted' => $converted];
            }
        }

        $changed = array_map(static fn (array $change): string => $change['table'] === 'settings'
            ? 'settings#'.$change['id']
            : $change['table'].'.'.$change['column'].'#'.$change['id'], $changes);

        if ($dryRun || $changes === []) {
            return new TrixContentMigrationReportDTO($checked, $changed, null);
        }

        $backupPath = $this->writeBackup($changes);

        DB::transaction(function () use ($changes): void {
            foreach ($changes as $change) {
                $this->write($change['table'], $change['column'], $change['id'], $change['converted']);
            }
        });

        return new TrixContentMigrationReportDTO($checked, $changed, $backupPath);
    }

    public function restore(string $backupPath): int
    {
        /** @var list<array{table: string, column: string, id: string, original: string, converted: string}>|null $changes */
        $changes = json_decode((string) File::get($backupPath), true);

        if (! is_array($changes)) {
            throw new RuntimeException("Not a rich text backup: {$backupPath}");
        }

        $restored = 0;

        DB::transaction(function () use ($changes, &$restored): void {
            foreach ($changes as $change) {
                // Never overwrite what an admin saved after the conversion
                if ($this->read($change['table'], $change['column'], $change['id']) !== $change['converted']) {
                    continue;
                }

                $this->write($change['table'], $change['column'], $change['id'], $change['original']);
                $restored++;
            }
        });

        return $restored;
    }

    /**
     * @param  list<array{table: string, column: string, id: string, original: string, converted: string}>  $changes
     */
    private function writeBackup(array $changes): string
    {
        File::ensureDirectoryExists($this->backupDirectory);
        $path = $this->backupDirectory.'/trix-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
        File::put($path, (string) json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function read(string $table, string $column, string $id): ?string
    {
        if ($table === 'settings') {
            $value = DB::table('settings')->where('key', $id)->value('value');
        } else {
            $value = DB::table($table)->where('id', $id)->value($column);
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Straight to the table: a conversion is not an edit (no updated_at, no model events).
     */
    private function write(string $table, string $column, string $id, string $value): void
    {
        if ($table === 'settings') {
            $this->settings->set($id, $value);

            return;
        }

        DB::table($table)->where('id', $id)->update([$column => $value]);
    }
}
