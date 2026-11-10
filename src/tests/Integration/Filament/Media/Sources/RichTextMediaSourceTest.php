<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Media\Sources;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Media\Sources\RichTextMediaSource;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\KnowledgeGuideResource;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

final class RichTextMediaSourceTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    /** Delivery URL base of this site (the test prefix is the default one, guildforge) */
    private const string BASE = 'https://res.cloudinary.com/demo/image/upload/';

    private function source(): RichTextMediaSource
    {
        return app(RichTextMediaSource::class);
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}> file id, record id, role and title of every usage
     */
    private function usages(): array
    {
        $rows = [];

        foreach ($this->source()->usages() as $usage) {
            $id = app(MediaFileIdResolverInterface::class)->fromStoredPath($usage->storedPath);
            $rows[] = [$id->value ?? '', $usage->recordId, $usage->role, $usage->title];
        }

        sort($rows);

        return $rows;
    }

    private function siteGuide(string $body, string $source = 'site'): KnowledgeGuideModel
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
            'conditions' => [],
            'related' => [],
            'featured' => false,
            'content_hash' => str_repeat('b', 40),
            'active' => true,
        ]);
    }

    public function test_declares_its_type_label_and_claims_no_folder(): void
    {
        $source = $this->source();

        $this->assertSame('rich_text', $source->type());
        $this->assertSame('Textos', $source->label());
        $this->assertSame([], $source->folders());
        $this->assertSame([], $source->excludedSubfolders());
        $this->assertFalse($source->hidden());
    }

    public function test_it_is_registered_without_claiming_any_folder(): void
    {
        $registry = app(MediaSourceRegistryInterface::class);

        $this->assertInstanceOf(RichTextMediaSource::class, $registry->source('rich_text'));
        $this->assertSame('events', $registry->claimingSource(new MediaFileId('events/2026/11/a'))?->type());
    }

    public function test_a_pasted_delivery_url_counts(): void
    {
        $event = EventModel::factory()->create([
            'title' => 'Torneo de otoño',
            'description' => '<p>Mira:</p><img src="'.self::BASE.'guildforge/events/2026/11/a.jpg" alt="">',
        ]);

        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/2026/11/a')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('events/2026/11/a2')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('events/2026/11/A')));
        $this->assertSame([['events/2026/11/a', 'events:'.$event->id, 'En el texto', 'Torneo de otoño']], $this->usages());
    }

    public function test_a_url_with_transformation_and_version_counts(): void
    {
        $article = ArticleModel::factory()->create([
            'title' => 'Crónica',
            'content' => '<p><a href="'.self::BASE.'c_fill,w_300,h_200/v1700000000/guildforge/articles/x.png">foto</a></p>',
        ]);

        $this->assertTrue($this->source()->usesFile(new MediaFileId('articles/x')));
        $this->assertSame([['articles/x', 'articles:'.$article->id, 'En el texto', 'Crónica']], $this->usages());
    }

    public function test_a_bare_url_in_the_text_counts(): void
    {
        EventModel::factory()->create([
            'description' => '<p>El cartel está en '.self::BASE.'v12/guildforge/events/cartel.webp. ¡Compártelo!</p>',
        ]);

        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/cartel')));
    }

    public function test_a_srcset_and_an_encoded_url_count(): void
    {
        EventModel::factory()->create([
            'description' => '<img srcset="'.self::BASE.'w_400/guildforge/events/small.jpg 1x, '.self::BASE.'w_800/guildforge/events/big.jpg 2x">'
                .'<img src="'.self::BASE.'guildforge/events/con%20espacio.jpg">',
        ]);
        $source = $this->source();

        $this->assertTrue($source->usesFile(new MediaFileId('events/small')));
        $this->assertTrue($source->usesFile(new MediaFileId('events/big')));
        $this->assertTrue($source->usesFile(new MediaFileId('events/con espacio')));
    }

    public function test_a_url_of_another_prefix_does_not_count(): void
    {
        EventModel::factory()->create([
            'description' => '<img src="'.self::BASE.'guildforge-smoke/events/a.jpg"><img src="https://example.com/guildforge/events/a.jpg">',
        ]);

        $this->assertFalse($this->source()->usesFile(new MediaFileId('events/a')));
        $this->assertSame([], $this->usages());
    }

    public function test_a_legal_text_in_the_settings_counts(): void
    {
        app(SettingsServiceInterface::class)->set('legal_terms_content', '<p><img src="'.self::BASE.'guildforge/branding/firma.png"></p>');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('branding/firma')));
        $this->assertSame([['branding/firma', 'settings:legal_terms_content', 'En el texto', 'Términos y condiciones']], $this->usages());
        $this->assertCount(1, $this->source()->usagesFor('settings:legal_terms_content'));
        $this->assertSame([], $this->source()->usagesFor('settings:guild_name'));
    }

    public function test_the_about_history_counts(): void
    {
        app(SettingsServiceInterface::class)->set('about_history', '<p><img src="'.self::BASE.'guildforge/about/local.jpg"></p>');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('about/local')));
    }

    public function test_the_body_of_a_site_guide_counts_and_an_official_one_is_not_read(): void
    {
        $guide = $this->siteGuide('<p><img src="'.self::BASE.'guildforge/events/guia.jpg"></p>');
        $this->siteGuide('<p><img src="'.self::BASE.'guildforge/events/oficial.jpg"></p>', source: 'core');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/guia')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('events/oficial')));
        $this->assertSame([['events/guia', 'guides:'.$guide->id, 'En el texto', 'Normas del local']], $this->usages());
    }

    public function test_usages_for_one_record_and_unknown_records(): void
    {
        $event = EventModel::factory()->create([
            'description' => '<img src="'.self::BASE.'guildforge/events/a.jpg"><img src="'.self::BASE.'guildforge/events/b.jpg">',
        ]);
        $source = $this->source();

        $this->assertCount(2, $source->usagesFor('events:'.$event->id));
        $this->assertSame([], $source->usagesFor('events:'.Str::uuid()->toString()));
        $this->assertSame([], $source->usagesFor('events:not-a-uuid'));
        $this->assertSame([], $source->usagesFor('users:'.$event->id));
        $this->assertSame([], $source->usagesFor($event->id));
    }

    public function test_the_same_file_twice_in_one_text_is_one_usage(): void
    {
        EventModel::factory()->create([
            'description' => '<img src="'.self::BASE.'guildforge/events/a.jpg"><a href="'.self::BASE.'v3/guildforge/events/a.jpg">ver</a>',
        ]);

        $this->assertCount(1, $this->usages());
    }

    public function test_edit_url_follows_each_record_permission(): void
    {
        $event = EventModel::factory()->create();
        $article = ArticleModel::factory()->create();
        $guide = $this->siteGuide('<p>x</p>');
        $source = $this->source();

        $events = $this->editorWithPermissions(['events.update']);
        $articles = $this->editorWithPermissions(['articles.update']);
        $knowledge = $this->editorWithPermissions(['knowledge.manage']);
        $pages = $this->editorWithPermissions(['pages.manage']);
        $nobody = $this->editorWithPermissions([]);

        $this->assertSame(EventResource::getUrl('edit', ['record' => $event]), $source->editUrl('events:'.$event->id, $events));
        $this->assertNull($source->editUrl('events:'.$event->id, $nobody));
        $this->assertSame(ArticleResource::getUrl('edit', ['record' => $article]), $source->editUrl('articles:'.$article->id, $articles));
        $this->assertNull($source->editUrl('articles:'.$article->id, $events));
        $this->assertSame(KnowledgeGuideResource::getUrl('edit', ['record' => $guide]), $source->editUrl('guides:'.$guide->id, $knowledge));
        $this->assertNull($source->editUrl('guides:'.$guide->id, $nobody));
        $this->assertSame(LegalPagesSettings::getUrl(), $source->editUrl('settings:legal_privacy_content', $pages));
        $this->assertSame(AboutPageSettings::getUrl(), $source->editUrl('settings:about_history', $pages));
        $this->assertNull($source->editUrl('settings:about_history', $nobody));
        $this->assertNull($source->editUrl('settings:guild_name', $pages));
        $this->assertNull($source->editUrl('nothing', $pages));
    }

    public function test_the_deletion_service_refuses_a_file_pasted_in_a_text(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        config(['media.delete_enabled' => true]);
        $catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $catalog);
        $catalog->add('events/2026/11/a', uploadedAt: '2026-10-01 10:00:00');
        MediaFileModel::query()->create([
            'file_id' => 'events/2026/11/a',
            'resource_type' => 'image',
            'folder' => 'events/2026/11/',
            'format' => 'jpg',
            'bytes' => 1000,
            'uploaded_at' => Carbon::parse('2026-10-01 10:00:00'),
            'last_seen_at' => Carbon::now(),
        ]);
        EventModel::factory()->create(['description' => '<p><img src="'.self::BASE.'guildforge/events/2026/11/a.jpg"></p>']);
        // The index lags behind: only the live check knows about the text
        MediaUsageModel::query()->delete();

        $result = app(MediaDeletionServiceInterface::class)->delete(
            [new MediaFileId('events/2026/11/a')],
            $this->editorWithPermissions(['media.view_any', 'media.delete']),
        );

        $this->assertSame(0, $result->deleted);
        $this->assertSame(['events/2026/11/a' => MediaDeletionRefusal::InUse], $result->refused);
        $this->assertArrayHasKey('events/2026/11/a', $catalog->assets);
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_saving_a_text_indexes_its_usages(): void
    {
        $event = EventModel::factory()->create(['description' => '<img src="'.self::BASE.'guildforge/events/a.jpg">']);

        $this->assertTrue(MediaUsageModel::query()
            ->where('source_type', 'rich_text')
            ->where('record_id', 'events:'.$event->id)
            ->where('file_id', 'events/a')
            ->exists());

        $event->update(['description' => '<p>Sin imagen</p>']);

        $this->assertFalse(MediaUsageModel::query()->where('source_type', 'rich_text')->exists());
    }

    public function test_saving_a_site_guide_indexes_its_usages(): void
    {
        $guide = $this->siteGuide('<img src="'.self::BASE.'guildforge/events/guia.jpg">');

        $this->assertTrue(MediaUsageModel::query()
            ->where('source_type', 'rich_text')
            ->where('record_id', 'guides:'.$guide->id)
            ->exists());
    }
}
