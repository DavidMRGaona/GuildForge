<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Content\Services;

use App\Application\Content\Services\TrixContentMigratorInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Content\Services\TrixContentMigrator;
use App\Infrastructure\Content\Services\TrixHtmlConverter;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
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

        $this->backups = sys_get_temp_dir().'/gf-richtext-'.bin2hex(random_bytes(8));
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
        $freshArticle = $article->fresh();
        $this->assertNotNull($freshArticle);
        $this->assertSame(self::TIPTAP, $freshArticle->content);
        $this->assertSame(self::TIPTAP, $event->fresh()?->description);
        $this->assertSame(self::TIPTAP, $untouched->fresh()?->description);
        $this->assertSame(self::TIPTAP, app(SettingsServiceInterface::class)->get('about_history'));
        // Not an edit: the record keeps its last modification date
        $this->assertSame($updatedAt, $freshArticle->updated_at?->toIso8601String());
        $this->assertFileExists((string) $report->backupPath);
        $this->assertStringContainsString(json_encode(self::TRIX, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), (string) File::get((string) $report->backupPath));

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

    public function test_a_backup_outside_the_backup_directory_is_refused(): void
    {
        // The backup directory exists: the file is refused for where it is, not because nothing is there
        File::ensureDirectoryExists($this->backups);
        $outside = sys_get_temp_dir().'/gf-richtext-outside-'.bin2hex(random_bytes(4)).'.json';
        File::put($outside, '[]');

        try {
            $this->expectException(RuntimeException::class);
            $this->migrator()->restore($outside);
        } finally {
            File::delete($outside);
        }
    }

    public function test_a_backup_is_found_by_its_file_name(): void
    {
        app(SettingsServiceInterface::class)->set('legal_terms_content', self::TRIX);
        $backup = (string) $this->migrator()->migrate()->backupPath;

        $this->assertSame(1, $this->migrator()->restore(basename($backup)));
        $this->assertSame(self::TRIX, DB::table('settings')->where('key', 'legal_terms_content')->value('value'));
    }

    public function test_restoring_never_writes_outside_the_converted_columns(): void
    {
        $user = UserModel::factory()->create(['name' => 'Ana']);
        app(SettingsServiceInterface::class)->set('site_name', 'GuildForge');
        File::ensureDirectoryExists($this->backups);
        File::put($this->backups.'/trix-edited-by-hand.json', json_encode([
            ['table' => 'users', 'column' => 'name', 'id' => (string) $user->id, 'original' => 'Mallory', 'converted' => 'Ana'],
            ['table' => 'settings', 'column' => 'value', 'id' => 'site_name', 'original' => 'Mallory', 'converted' => 'GuildForge'],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(0, $this->migrator()->restore($this->backups.'/trix-edited-by-hand.json'));
        $this->assertSame('Ana', $user->fresh()?->name);
        $this->assertSame('GuildForge', DB::table('settings')->where('key', 'site_name')->value('value'));
    }

    public function test_a_relative_path_cannot_climb_out_of_the_backup_directory(): void
    {
        File::ensureDirectoryExists($this->backups);
        $sibling = $this->backups.'-sibling';
        File::ensureDirectoryExists($sibling);
        File::put($sibling.'/trix-x.json', '[]');

        try {
            $this->expectException(RuntimeException::class);
            $this->migrator()->restore('../'.basename($sibling).'/trix-x.json');
        } finally {
            File::deleteDirectory($sibling);
        }
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
        return new TrixContentMigrator(new TrixHtmlConverter, app(SettingsServiceInterface::class), $this->backups);
    }
}
