<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Content\Services;

use App\Application\Content\Services\TrixContentMigratorInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Content\Services\TrixContentMigrator;
use App\Infrastructure\Content\Services\TrixHtmlConverter;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class TrixContentMigratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string TRIX = '<div>Hola<br><br>mundo <del>viejo</del></div>';

    private const string TIPTAP = '<p>Hola</p><p>mundo <s>viejo</s></p>';

    private string $backups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backups = sys_get_temp_dir().'/gf-richtext-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backups);
        parent::tearDown();
    }

    public function test_a_dry_run_lists_the_changes_and_writes_nothing(): void
    {
        $article = ArticleModel::factory()->create(['content' => self::TRIX]);
        app(SettingsServiceInterface::class)->set('legal_terms_content', self::TRIX);

        $report = $this->migrator()->migrate(dryRun: true);

        $this->assertSame(["articles.content#{$article->id}", 'settings#legal_terms_content'], $report->changed);
        $this->assertNull($report->backupPath);
        $this->assertSame(self::TRIX, $article->fresh()?->content);
        $this->assertSame(self::TRIX, app(SettingsServiceInterface::class)->get('legal_terms_content'));
        $this->assertDirectoryDoesNotExist($this->backups);
    }

    public function test_it_converts_every_rich_text_value_once_and_keeps_a_backup(): void
    {
        $article = ArticleModel::factory()->create(['content' => self::TRIX, 'updated_at' => now()->subYear()]);
        $event = EventModel::factory()->create(['description' => self::TRIX]);
        $untouched = EventModel::factory()->create(['description' => self::TIPTAP]);
        app(SettingsServiceInterface::class)->set('about_history', self::TRIX);
        $updatedAt = $article->fresh()?->updated_at?->toIso8601String();

        $report = $this->migrator()->migrate();

        $this->assertCount(3, $report->changed);
        $this->assertSame(self::TIPTAP, $article->fresh()?->content);
        $this->assertSame(self::TIPTAP, $event->fresh()?->description);
        $this->assertSame(self::TIPTAP, $untouched->fresh()?->description);
        $this->assertSame(self::TIPTAP, app(SettingsServiceInterface::class)->get('about_history'));
        // Not an edit: the record keeps its last modification date
        $this->assertSame($updatedAt, $article->fresh()?->updated_at?->toIso8601String());
        $this->assertFileExists((string) $report->backupPath);
        $this->assertStringContainsString(json_encode(self::TRIX, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', (string) File::get((string) $report->backupPath));

        $again = $this->migrator()->migrate();

        $this->assertSame([], $again->changed);
        $this->assertNull($again->backupPath);
    }

    public function test_settings_are_read_from_the_table_not_from_the_cache(): void
    {
        app(SettingsServiceInterface::class)->set('legal_terms_content', self::TRIX);
        // A stale settings cache (seeders write settings without clearing it)
        Cache::forever('site_settings', []);

        $report = $this->migrator()->migrate();

        $this->assertSame(['settings#legal_terms_content'], $report->changed);
        $this->assertSame(self::TIPTAP, DB::table('settings')->where('key', 'legal_terms_content')->value('value'));
    }

    public function test_restoring_a_backup_skips_what_was_edited_since(): void
    {
        $edited = ArticleModel::factory()->create(['content' => self::TRIX]);
        $kept = ArticleModel::factory()->create(['content' => self::TRIX]);
        $backup = (string) $this->migrator()->migrate()->backupPath;
        DB::table('articles')->where('id', $edited->id)->update(['content' => '<p>Editado después</p>']);

        $restored = $this->migrator()->restore($backup);

        $this->assertSame(1, $restored);
        $this->assertSame(self::TRIX, $kept->fresh()?->content);
        $this->assertSame('<p>Editado después</p>', $edited->fresh()?->content);
    }

    public function test_the_migration_converts_on_deploy(): void
    {
        $this->app->instance(TrixContentMigratorInterface::class, $this->migrator());
        $article = ArticleModel::factory()->create(['content' => self::TRIX]);

        (require database_path('migrations/2026_10_08_000001_convert_trix_rich_text.php'))->up();

        $this->assertSame(self::TIPTAP, $article->fresh()?->content);
    }

    private function migrator(): TrixContentMigrator
    {
        return new TrixContentMigrator(new TrixHtmlConverter(), app(SettingsServiceInterface::class), $this->backups);
    }
}
