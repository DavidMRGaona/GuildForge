<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Contracts\ConcealsUnusedMediaFiles;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\DTOs\MediaDeletionResultDTO;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Media\Sources\ArticleTextImageMediaSource;
use App\Filament\Media\Sources\RichTextMediaSource;
use App\Http\Controllers\EditorImageUploadController;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

/**
 * The images inserted with the editor of the core texts live in "<origin>/content/" (core#481). The rich text
 * source reads those texts, so it claims the folders no other source claims (core#838): their files are shown
 * with their thumbnail, count as unused when no text names them, and can be deleted as any other file.
 *
 * The articles' folder also takes the images of the web editor, where members write their proposals: one left
 * unused may come from a draft only its author saw, so a source of its own conceals the unused ones, as for
 * the images of the web proposal forms (core#841). Used ones show and every one is deleted alike.
 */
final class EditorImageMediaTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    /** Delivery URL base of this site (the test prefix is the default one, guildforge) */
    private const string BASE = 'https://res.cloudinary.com/demo/image/upload/';

    private InMemoryMediaCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        // Past the 7-day grace of a file uploaded on 1 November
        Carbon::setTestNow('2026-11-20 12:00:00');
        config(['media.delete_enabled' => true]);
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
        Storage::fake('images');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function claimedOrigins(): iterable
    {
        yield from self::panelOnlyOrigins();
        yield 'article contents' => ['articles'];
    }

    /**
     * Folders only the panel's editors upload to: the web editor stores into the articles' one alone.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function panelOnlyOrigins(): iterable
    {
        yield 'event descriptions' => ['events'];
        yield 'site guides' => ['knowledge'];
        yield 'legal texts' => ['legal'];
    }

    #[DataProvider('panelOnlyOrigins')]
    public function test_the_rich_text_source_claims_the_editor_folder(string $origin): void
    {
        $registry = app(MediaSourceRegistryInterface::class);

        $this->assertSame(RichTextMediaSource::TYPE, $registry->claimingSource(new MediaFileId($origin.'/content/2026/11/a'))?->type());
    }

    public function test_the_articles_editor_folder_has_a_source_that_conceals_its_unused_files(): void
    {
        $source = app(MediaSourceRegistryInterface::class)->claimingSource(new MediaFileId('articles/content/2026/11/a'));

        $this->assertInstanceOf(ArticleTextImageMediaSource::class, $source);
        $this->assertContains(ConcealsUnusedMediaFiles::class, class_implements($source));
        $this->assertContains(ShowsMediaPreviews::class, class_implements($source));
    }

    public function test_the_web_editor_uploads_into_the_articles_folder_only(): void
    {
        // Were another route to store the web editor's images, its folder would need the articles' treatment
        $uploads = array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            static fn (LaravelRoute $route): bool => $route->getActionName() === EditorImageUploadController::class,
        ));

        $this->assertSame(['editor-images.articles.store'], array_map(static fn (LaravelRoute $route): ?string => $route->getName(), $uploads));

        $this->actingAs($this->editorWithPermissions(['articles.create']))
            ->post('/editor/imagenes/articulos', ['image' => UploadedFile::fake()->image('foto.png')])
            ->assertCreated()
            ->assertJsonPath('id', fn (string $id): bool => str_starts_with($id, EditorImageOrigin::Articles->folder()->claim()));
    }

    public function test_every_editor_folder_has_a_source(): void
    {
        $registry = app(MediaSourceRegistryInterface::class);

        foreach (EditorImageOrigin::cases() as $origin) {
            $this->assertNotNull($registry->claimingSource(new MediaFileId($origin->folder()->claim().'2026/11/a')), $origin->value);
        }
    }

    public function test_the_other_folders_keep_their_sources(): void
    {
        $registry = app(MediaSourceRegistryInterface::class);

        $this->assertSame('events', $registry->claimingSource(new MediaFileId('events/2026/11/a'))?->type());
        $this->assertSame('articles', $registry->claimingSource(new MediaFileId('articles/2026/11/a'))?->type());
        $this->assertSame('core.proposal-images', $registry->claimingSource(new MediaFileId('events/proposals/2026/11/a'))?->type());
        $this->assertSame('settings', $registry->claimingSource(new MediaFileId('about/content/2026/11/a'))?->type());
        $this->assertSame('footer', $registry->claimingSource(new MediaFileId('footer/content/2026/11/a'))?->type());
        // Only the editor's own subfolder: nothing else under knowledge/ or legal/ is claimed
        $this->assertNull($registry->claimingSource(new MediaFileId('knowledge/2026/11/a')));
        $this->assertNull($registry->claimingSource(new MediaFileId('legal/2026/11/a')));
    }

    #[DataProvider('panelOnlyOrigins')]
    public function test_an_image_no_text_names_is_unused_shown_and_deleted(string $origin): void
    {
        $file = $origin.'/content/2026/11/'.Str::uuid()->toString();
        $this->catalog->add($file, uploadedAt: '2026-11-01 10:00:00');
        $this->sync();

        $this->assertSame(MediaFileStatus::Unused, $this->library()->statusOf($this->file($file)));
        $this->assertFalse($this->library()->conceals($this->file($file)));

        $result = $this->deleteFile($file);

        $this->assertSame(1, $result->deleted);
        $this->assertSame([], $result->refused);
        $this->assertArrayNotHasKey($file, $this->catalog->assets);
        $this->assertSame(1, MediaDeletionModel::query()->where('file_id', $file)->count());
    }

    public function test_an_unused_image_of_the_articles_folder_is_concealed_and_deleted(): void
    {
        // Uploaded from the web editor for a draft that was never saved, or taken out of it
        $file = 'articles/content/2026/11/'.Str::uuid()->toString();
        $this->catalog->add($file, uploadedAt: '2026-11-01 10:00:00');
        $this->sync();

        $this->assertSame(MediaFileStatus::Unused, $this->library()->statusOf($this->file($file)));
        $this->assertTrue($this->library()->conceals($this->file($file)));
        $this->assertSame([], $this->library()->applyReusable($this->library()->query())->pluck('file_id')->all());

        $result = $this->deleteFile($file);

        $this->assertSame(1, $result->deleted);
        $this->assertArrayNotHasKey($file, $this->catalog->assets);
        // The deletions list never names it either
        $this->assertTrue(MediaDeletionModel::query()->where('file_id', $file)->sole()->is_hidden);
    }

    #[DataProvider('claimedOrigins')]
    public function test_an_image_its_text_names_is_in_use_shown_and_never_deleted(string $origin): void
    {
        $file = $origin.'/content/2026/11/'.Str::uuid()->toString();
        $this->catalog->add($file, uploadedAt: '2026-11-01 10:00:00');
        $this->writeText($origin, '<p>Mira:</p><img src="'.self::BASE.'guildforge/'.$file.'.jpg" data-id="'.$file.'.jpg" alt="">');
        $this->sync();

        $this->assertSame(MediaFileStatus::InUse, $this->library()->statusOf($this->file($file)));
        $this->assertFalse($this->library()->conceals($this->file($file)));
        $this->assertTrue(MediaUsageModel::query()->where('file_id', $file)->where('source_type', RichTextMediaSource::TYPE)->exists());

        // The index lags behind: only the live check knows about the text
        MediaUsageModel::query()->delete();
        $result = $this->deleteFile($file);

        $this->assertSame(0, $result->deleted);
        $this->assertSame([$file => MediaDeletionRefusal::InUse], $result->refused);
        $this->assertArrayHasKey($file, $this->catalog->assets);
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_an_image_named_by_the_text_of_another_origin_is_never_deleted(): void
    {
        $file = 'legal/content/2026/11/'.Str::uuid()->toString();
        $this->catalog->add($file, uploadedAt: '2026-11-01 10:00:00');
        // Pasted in an article: any text read by the source protects it, whatever its folder
        $this->writeText('articles', '<p><img src="'.self::BASE.'guildforge/'.$file.'.jpg" alt=""></p>');
        $this->sync();
        MediaUsageModel::query()->delete();

        $result = $this->deleteFile($file);

        $this->assertSame([$file => MediaDeletionRefusal::InUse], $result->refused);
        $this->assertArrayHasKey($file, $this->catalog->assets);
    }

    public function test_an_image_of_an_unsubmitted_draft_stays_concealed_and_in_use(): void
    {
        $file = 'articles/content/2026/11/'.Str::uuid()->toString();
        $this->catalog->add($file, uploadedAt: '2026-11-01 10:00:00');
        ArticleModel::factory()->proposal()->create([
            'title' => 'Mi borrador secreto',
            'content' => '<p><img src="'.self::BASE.'guildforge/'.$file.'.jpg" alt=""></p>',
        ]);
        $this->sync();

        // Its author's alone until submitted (core#837)
        $this->assertSame(MediaFileStatus::InUse, $this->library()->statusOf($this->file($file)));
        $this->assertTrue($this->library()->conceals($this->file($file)));
        $this->assertSame([$file => MediaDeletionRefusal::InUse], $this->deleteFile($file)->refused);
    }

    public function test_an_image_inside_the_grace_period_is_recent_and_never_deleted(): void
    {
        $file = 'events/content/2026/11/'.Str::uuid()->toString();
        $this->catalog->add($file, uploadedAt: '2026-11-18 10:00:00');
        $this->sync();

        $this->assertSame(MediaFileStatus::Recent, $this->library()->statusOf($this->file($file)));
        $this->assertSame([$file => MediaDeletionRefusal::Recent], $this->deleteFile($file)->refused);
        $this->assertArrayHasKey($file, $this->catalog->assets);
    }

    private function writeText(string $origin, string $html): void
    {
        match ($origin) {
            'events' => EventModel::factory()->create(['title' => 'Torneo', 'description' => $html]),
            'articles' => ArticleModel::factory()->create(['title' => 'Crónica', 'content' => $html]),
            'knowledge' => KnowledgeGuideModel::query()->create([
                'guide_id' => 'site.normas-'.Str::random(6),
                'locale' => 'es',
                'source' => 'site',
                'module' => null,
                'audience' => 'site',
                'category_id' => 'community',
                'sort_order' => 10,
                'title' => 'Normas del local',
                'summary' => 'Resumen',
                'keywords' => [],
                'body_format' => 'html',
                'body' => $html,
                'body_text' => 'Cuerpo.',
                'conditions' => [],
                'related' => [],
                'featured' => false,
                'content_hash' => str_repeat('b', 40),
                'active' => true,
            ]),
            'legal' => app(SettingsServiceInterface::class)->set('legal_privacy_content', $html),
            default => $this->fail('Unknown origin '.$origin),
        };
    }

    private function deleteFile(string $file): MediaDeletionResultDTO
    {
        return app(MediaDeletionServiceInterface::class)->delete(
            [new MediaFileId($file)],
            $this->editorWithPermissions(['media.view_any', 'media.delete']),
        );
    }

    private function sync(): void
    {
        $result = app(MediaIndexerInterface::class)->sync();
        $this->assertSame([], $result->failedSources);
    }

    private function file(string $id): MediaFileModel
    {
        return MediaFileModel::query()->where('file_id', $id)->firstOrFail();
    }

    private function library(): MediaLibraryQueryInterface
    {
        return app(MediaLibraryQueryInterface::class);
    }
}
