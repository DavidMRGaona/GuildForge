<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Content\Services;

use App\Application\Content\Services\EditorImageMoverInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Images inserted with the rich editor before core#481 live on the local "public" disk: they move
 * to Cloudinary, the HTML points at them there and the local copy goes once every value is saved.
 */
final class EditorImageMoverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    private Filesystem $images;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 12:00:00');
        Storage::fake('public');
        $this->images = Storage::fake('images', ['url' => 'https://res.cloudinary.com/demo/image/upload/guildforge']);
    }

    public function test_an_article_image_moves_to_cloudinary(): void
    {
        $this->localFile('3fJk2Lm9Qx.png', 'png-bytes', '2025-03-05 10:00:00');
        $article = $this->article('<p>Hola</p><p><img src="'.$this->localUrl('3fJk2Lm9Qx.png').'" data-id="3fJk2Lm9Qx.png" alt="Mesa"></p>');
        $updatedAt = $this->updatedAt('articles', $article->id);

        $report = $this->mover()->move();

        $content = $this->column('articles', 'content', $article->id);
        $path = $this->onlyImage();
        $this->assertMatchesRegularExpression('~^articles/content/2025/03/'.self::UUID.'\.png$~', $path);
        $this->assertSame('png-bytes', $this->images->get($path));
        $this->assertSame('<p>Hola</p><p><img src="'.$this->images->url($path).'" data-id="'.$path.'" alt="Mesa"></p>', $content);
        Storage::disk('public')->assertMissing('3fJk2Lm9Qx.png');
        $this->assertSame($updatedAt, $this->updatedAt('articles', $article->id));
        $this->assertSame(1, $report->checked);
        $this->assertSame(['articles.content#'.$article->id.': '.$this->localUrl('3fJk2Lm9Qx.png').' → '.$path], $report->moved);
        $this->assertSame([], $report->broken);
        $this->assertSame([], $report->failed);
    }

    public function test_a_relative_url_without_id_moves_and_gains_its_id(): void
    {
        $this->localFile('trix/foto.jpg', 'jpg-bytes');
        $event = EventModel::factory()->create(['description' => '<p><img src="/storage/trix/foto.jpg" alt="Foto"></p>']);

        $this->mover()->move();

        $path = $this->onlyImage();
        $this->assertMatchesRegularExpression('~^events/content/2026/11/'.self::UUID.'\.jpg$~', $path);
        $this->assertSame('<p><img src="'.$this->images->url($path).'" alt="Foto" data-id="'.$path.'"></p>', $this->column('events', 'description', $event->id));
        Storage::disk('public')->assertMissing('trix/foto.jpg');
    }

    public function test_settings_and_site_guides_move_to_their_own_folders(): void
    {
        $this->localFile('a.png', 'a');
        $this->localFile('b.png', 'b');
        $this->localFile('c.png', 'c');
        $settings = app(SettingsServiceInterface::class);
        $settings->set('about_history', '<p><img src="/storage/a.png"></p>');
        $settings->set('legal_terms_content', '<p><img src="/storage/b.png"></p>');
        $guide = $this->guide('<p><img src="/storage/c.png"></p>', 'site');
        $official = $this->guide('<p><img src="/storage/d.png"></p>', 'core');

        $this->mover()->move();

        $this->assertMatchesRegularExpression('~src="[^"]*/about/content/2026/11/'.self::UUID.'~', $this->setting('about_history'));
        $this->assertMatchesRegularExpression('~src="[^"]*/legal/content/2026/11/'.self::UUID.'~', $this->setting('legal_terms_content'));
        $this->assertMatchesRegularExpression('~src="[^"]*/knowledge/content/2026/11/'.self::UUID.'~', $this->column('knowledge_guides', 'body', $guide->id));
        $this->assertSame('<p><img src="/storage/d.png"></p>', $this->column('knowledge_guides', 'body', $official->id));
        $this->assertCount(3, $this->images->allFiles());
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->localFile('3fJk2Lm9Qx.png', 'png-bytes');
        $html = '<p><img src="/storage/3fJk2Lm9Qx.png" data-id="3fJk2Lm9Qx.png"></p>';
        $article = $this->article($html);

        $report = $this->mover()->move(dryRun: true);

        $this->assertSame($html, $this->column('articles', 'content', $article->id));
        Storage::disk('public')->assertExists('3fJk2Lm9Qx.png');
        $this->assertSame([], $this->images->allFiles());
        $this->assertCount(1, $report->moved);
        $this->assertMatchesRegularExpression('~^articles\.content#'.$article->id.': /storage/3fJk2Lm9Qx\.png → articles/content/2026/11/'.self::UUID.'\.png$~', $report->moved[0]);
    }

    public function test_a_second_run_finds_nothing_to_move(): void
    {
        $this->localFile('3fJk2Lm9Qx.png', 'png-bytes');
        $article = $this->article('<p><img src="/storage/3fJk2Lm9Qx.png" data-id="3fJk2Lm9Qx.png"></p>');
        $this->mover()->move();
        $moved = $this->column('articles', 'content', $article->id);

        $report = $this->mover()->move();

        $this->assertSame($moved, $this->column('articles', 'content', $article->id));
        $this->assertSame([], $report->moved);
        $this->assertSame([], $report->broken);
        $this->assertSame([], $report->failed);
        $this->assertCount(1, $this->images->allFiles());
    }

    public function test_the_same_file_uploaded_again_lands_on_the_same_asset(): void
    {
        // A run cut between the upload and the save uploads again: the asset is overwritten, not duplicated
        $html = '<p><img src="/storage/3fJk2Lm9Qx.png"></p>';
        $this->localFile('3fJk2Lm9Qx.png', 'png-bytes');
        $article = $this->article($html);
        $this->mover()->move();
        $moved = $this->column('articles', 'content', $article->id);

        DB::table('articles')->where('id', $article->id)->update(['content' => $html]);
        $this->localFile('3fJk2Lm9Qx.png', 'png-bytes');
        $this->mover()->move();

        $this->assertSame($moved, $this->column('articles', 'content', $article->id));
        $this->assertCount(1, $this->images->allFiles());
    }

    public function test_a_failed_upload_keeps_the_html_and_the_local_copy(): void
    {
        $this->localFile('3fJk2Lm9Qx.png', 'png-bytes');
        $html = '<p><img src="/storage/3fJk2Lm9Qx.png" data-id="3fJk2Lm9Qx.png"></p>';
        $article = $this->article($html);
        $images = Mockery::mock($this->images);
        $images->shouldReceive('put')->andThrow(new RuntimeException('Upload refused'));
        Storage::set('images', $images);

        $report = $this->mover()->move();

        $this->assertSame($html, $this->column('articles', 'content', $article->id));
        Storage::disk('public')->assertExists('3fJk2Lm9Qx.png');
        $this->assertSame([], $report->moved);
        $this->assertSame(['articles.content#'.$article->id.': /storage/3fJk2Lm9Qx.png (Upload refused)'], $report->failed);
    }

    public function test_an_upload_answering_false_is_a_failure(): void
    {
        $this->localFile('3fJk2Lm9Qx.png', 'png-bytes');
        $html = '<p><img src="/storage/3fJk2Lm9Qx.png"></p>';
        $article = $this->article($html);
        $images = Mockery::mock($this->images);
        $images->shouldReceive('put')->andReturnFalse();
        Storage::set('images', $images);

        $report = $this->mover()->move();

        $this->assertSame($html, $this->column('articles', 'content', $article->id));
        Storage::disk('public')->assertExists('3fJk2Lm9Qx.png');
        $this->assertCount(1, $report->failed);
    }

    public function test_a_shared_file_stays_until_every_value_using_it_is_saved(): void
    {
        $this->localFile('shared.png', 'png-bytes');
        $article = $this->article('<p><img src="/storage/shared.png"></p>');
        $event = EventModel::factory()->create(['description' => '<p><img src="/storage/shared.png"></p>']);
        $images = $this->images;
        $failing = Mockery::mock($images);
        $failing->shouldReceive('put')->andReturnUsing(static function (string $path, string $contents) use ($images): bool {
            if (str_starts_with($path, 'events/')) {
                throw new RuntimeException('Upload refused');
            }

            return $images->put($path, $contents);
        });
        Storage::set('images', $failing);

        $report = $this->mover()->move();

        $this->assertStringContainsString('/articles/content/', $this->column('articles', 'content', $article->id));
        $this->assertSame('<p><img src="/storage/shared.png"></p>', $this->column('events', 'description', $event->id));
        Storage::disk('public')->assertExists('shared.png');
        $this->assertCount(1, $report->moved);
        $this->assertCount(1, $report->failed);
    }

    public function test_a_shared_file_goes_once_every_value_is_saved(): void
    {
        $this->localFile('shared.png', 'png-bytes');
        $first = $this->article('<p><img src="/storage/shared.png"></p>');
        $second = $this->article('<p><img src="/storage/shared.png"></p>');

        $report = $this->mover()->move();

        $this->assertSame($this->column('articles', 'content', $first->id), $this->column('articles', 'content', $second->id));
        Storage::disk('public')->assertMissing('shared.png');
        $this->assertCount(1, $this->images->allFiles());
        $this->assertCount(2, $report->moved);
    }

    public function test_broken_references_are_reported_and_left_alone(): void
    {
        $this->localFile('ok.png', 'png-bytes');
        $article = $this->article('<p><img src="/storage/missing.png"><img src="/storage/../.env"><img src="/storage/ok.png"></p>');

        $report = $this->mover()->move();

        $content = $this->column('articles', 'content', $article->id);
        $this->assertStringContainsString('<img src="/storage/missing.png">', $content);
        $this->assertStringContainsString('<img src="/storage/../.env">', $content);
        $this->assertStringNotContainsString('/storage/ok.png', $content);
        $this->assertSame([
            'articles.content#'.$article->id.': /storage/missing.png',
            'articles.content#'.$article->id.': /storage/../.env',
        ], $report->broken);
        $this->assertCount(1, $report->moved);
    }

    public function test_an_image_on_a_previous_host_of_the_site_is_reported_with_the_host_to_pass(): void
    {
        $this->localFile('3fJk2Lm9Qx.png', 'png-bytes');
        $html = '<p><img src="https://old.example/storage/3fJk2Lm9Qx.png"></p>';
        $article = $this->article($html);

        $report = $this->mover()->move();

        $this->assertSame($html, $this->column('articles', 'content', $article->id));
        Storage::disk('public')->assertExists('3fJk2Lm9Qx.png');
        $this->assertSame([], $report->moved);
        $this->assertSame([], $report->broken);
        $this->assertSame(['articles.content#'.$article->id.': https://old.example/storage/3fJk2Lm9Qx.png (file found here: pass --host=old.example)'], $report->otherHosts);
    }

    public function test_an_image_on_a_host_passed_moves(): void
    {
        $this->localFile('a.png', 'a');
        $this->localFile('b.png', 'b');
        $first = $this->article('<p><img src="https://old.example/storage/a.png"></p>');
        // Hosts compare without case and port
        $second = $this->article('<p><img src="http://OLD.example:8443/storage/b.png"></p>');

        $report = $this->mover()->move(hosts: ['old.example']);

        $this->assertStringContainsString('/articles/content/2026/11/', $this->column('articles', 'content', $first->id));
        $this->assertStringContainsString('/articles/content/2026/11/', $this->column('articles', 'content', $second->id));
        Storage::disk('public')->assertMissing('a.png');
        Storage::disk('public')->assertMissing('b.png');
        $this->assertCount(2, $report->moved);
        $this->assertSame([], $report->otherHosts);
    }

    public function test_an_image_of_another_site_without_a_local_file_is_reported_and_left_alone(): void
    {
        $html = '<p><img src="https://elsewhere.example/storage/photo.png"></p>';
        $article = $this->article($html);

        $report = $this->mover()->move();

        $this->assertSame($html, $this->column('articles', 'content', $article->id));
        $this->assertSame([], $report->broken);
        $this->assertSame(['articles.content#'.$article->id.': https://elsewhere.example/storage/photo.png (no such file here)'], $report->otherHosts);
    }

    public function test_a_missing_file_on_a_host_passed_is_a_broken_reference(): void
    {
        $html = '<p><img src="https://old.example/storage/missing.png"></p>';
        $article = $this->article($html);

        $report = $this->mover()->move(hosts: ['old.example']);

        $this->assertSame($html, $this->column('articles', 'content', $article->id));
        $this->assertSame(['articles.content#'.$article->id.': https://old.example/storage/missing.png'], $report->broken);
        $this->assertSame([], $report->otherHosts);
    }

    public function test_other_images_are_not_touched(): void
    {
        $cloudinary = $this->images->url('articles/content/2026/11/0b7e7c1e-6a0f-4c56-9d0e-1b2f3a4c5d6e.png');
        $html = '<p><img src="https://example.org/foto.png"><img src="'.$cloudinary.'" data-id="articles/content/2026/11/0b7e7c1e-6a0f-4c56-9d0e-1b2f3a4c5d6e.png"><img src="data:image/png;base64,AAAA"><img alt="sin origen"></p>';
        $article = $this->article($html);

        $report = $this->mover()->move();

        $this->assertSame($html, $this->column('articles', 'content', $article->id));
        $this->assertSame([], $report->moved);
        $this->assertSame([], $report->broken);
    }

    private function mover(): EditorImageMoverInterface
    {
        return app(EditorImageMoverInterface::class);
    }

    private function localFile(string $path, string $contents, ?string $modifiedAt = null): void
    {
        Storage::disk('public')->put($path, $contents);

        if ($modifiedAt !== null) {
            touch(Storage::disk('public')->path($path), (int) strtotime($modifiedAt.' UTC'));
        }
    }

    private function localUrl(string $path): string
    {
        return rtrim((string) config('filesystems.disks.public.url'), '/').'/'.$path;
    }

    private function article(string $content): ArticleModel
    {
        return ArticleModel::factory()->create(['content' => $content]);
    }

    private function guide(string $body, string $source): KnowledgeGuideModel
    {
        return KnowledgeGuideModel::query()->create([
            'guide_id' => $source === 'site' ? 'site.normas-'.Str::random(6) : 'core.site.example-'.Str::random(6),
            'locale' => 'es',
            'source' => $source,
            'module' => null,
            'audience' => 'site',
            'category_id' => 'community',
            'sort_order' => 10,
            'title' => 'Normas del local',
            'summary' => 'Resumen',
            'keywords' => [],
            'body_format' => 'html',
            'body' => $body,
            'body_text' => 'Cuerpo.',
            'content_hash' => sha1($body),
            'conditions' => [],
            'related' => [],
        ]);
    }

    private function onlyImage(): string
    {
        $files = $this->images->allFiles();
        $this->assertCount(1, $files);

        return $files[0];
    }

    private function column(string $table, string $column, string $id): string
    {
        return (string) DB::table($table)->where('id', $id)->value($column);
    }

    private function setting(string $key): string
    {
        return (string) DB::table('settings')->where('key', $key)->value('value');
    }

    private function updatedAt(string $table, string $id): mixed
    {
        return DB::table($table)->where('id', $id)->value('updated_at');
    }
}
