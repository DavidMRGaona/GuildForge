<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Media\Sources;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Footer\ValueObjects\FooterBlock;
use App\Domain\Footer\ValueObjects\FooterColumn;
use App\Domain\Footer\ValueObjects\FooterLayout;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Media\Sources\FooterMediaSource;
use App\Filament\Pages\Settings\FooterSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class FooterMediaSourceTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const string BASE = 'https://res.cloudinary.com/demo/image/upload/';

    private function source(): FooterMediaSource
    {
        return app(FooterMediaSource::class);
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

    private function store(FooterColumn ...$columns): void
    {
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of(array_values($columns)));
    }

    public function test_it_claims_the_footer_folder_with_its_trailing_slash(): void
    {
        $source = $this->source();

        $this->assertSame('footer', $source->type());
        $this->assertSame('Pie de página', $source->label());
        $this->assertSame(['footer/'], $source->folders());
        $this->assertSame([], $source->excludedSubfolders());
        $this->assertFalse($source->hidden());
    }

    public function test_the_editor_images_and_the_logos_no_longer_have_an_unknown_origin(): void
    {
        $registry = app(MediaSourceRegistryInterface::class);

        $this->assertInstanceOf(FooterMediaSource::class, $registry->source('footer'));
        $this->assertSame('footer', $registry->claimingSource(new MediaFileId('footer/content/2026/11/a'))?->type());
        $this->assertSame('footer', $registry->claimingSource(new MediaFileId('footer/logos/b'))?->type());
        // «footer» as a prefix of another folder name is not the footer's
        $this->assertNull($registry->claimingSource(new MediaFileId('footers/x')));
    }

    public function test_the_logos_count_as_uses_with_the_text_that_tells_them_apart(): void
    {
        $this->store(new FooterColumn([
            new FooterBlock('core.logos', null, ['logos' => [
                ['image' => 'footer/logos/a.png', 'alt' => 'Federación', 'url' => ''],
                ['image' => 'events/2026/11/shared.png', 'alt' => 'Club', 'url' => 'https://example.com'],
                ['image' => 'footer/logos/sin-texto.png', 'alt' => '', 'url' => ''],
            ]]),
        ]));

        $this->assertSame([
            ['events/2026/11/shared', 'layout', 'Logotipo del pie', 'Pie de página · logotipo «Club»'],
            ['footer/logos/a', 'layout', 'Logotipo del pie', 'Pie de página · logotipo «Federación»'],
            ['footer/logos/sin-texto', 'layout', 'Logotipo del pie', 'Pie de página · logotipo «footer/logos/sin-texto.png»'],
        ], $this->usages());
        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/2026/11/shared')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('events/2026/11/other')));
    }

    public function test_every_delivery_url_in_the_text_blocks_counts_wherever_the_file_lives(): void
    {
        $this->store(
            new FooterColumn([new FooterBlock('core.text', null, ['content' => '<p>Hola <img src="'.self::BASE.'c_fill,w_300/v1700000000/guildforge/footer/content/2026/11/editor.jpg"></p>'])]),
            new FooterColumn([
                new FooterBlock('core.links', null, ['menu' => 'footer']),
                new FooterBlock('core.text', 'Cartel', ['content' => '<p><a href="'.self::BASE.'v12/guildforge/events/2026/11/cartel.webp">Cartel</a></p>']),
            ]),
        );

        $this->assertSame([
            ['events/2026/11/cartel', 'layout', 'En el texto', 'Pie de página · texto de la columna 2'],
            ['footer/content/2026/11/editor', 'layout', 'En el texto', 'Pie de página · texto de la columna 1'],
        ], $this->usages());
        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/2026/11/cartel')));
        $this->assertTrue($this->source()->usesFile(new MediaFileId('footer/content/2026/11/editor')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('events/2026/11/other')));
    }

    public function test_usages_of_a_record_are_those_of_the_layout_only(): void
    {
        $this->store(new FooterColumn([new FooterBlock('core.logos', null, ['logos' => [['image' => 'footer/logos/a.png', 'alt' => 'Club', 'url' => '']]])]));

        $this->assertCount(1, $this->source()->usagesFor('layout'));
        $this->assertSame([], $this->source()->usagesFor('otro'));
        $this->assertSame([], $this->source()->usagesFor(''));
    }

    public function test_without_a_layout_there_are_no_uses(): void
    {
        $this->assertSame([], $this->usages());
        $this->assertFalse($this->source()->usesFile(new MediaFileId('footer/logos/a')));
    }

    public function test_it_reads_the_table_not_the_settings_cache(): void
    {
        $this->store(new FooterColumn([new FooterBlock('core.logos', null, ['logos' => [['image' => 'footer/logos/a.png', 'alt' => 'Club', 'url' => '']]])]));
        app(FooterLayoutServiceInterface::class)->layout();
        DB::table('settings')->where('key', 'footer_layout')->update(['value' => '']);

        $this->assertSame([], $this->usages());
    }

    public function test_a_row_that_cannot_be_read_fails_instead_of_looking_unused(): void
    {
        $this->store(new FooterColumn([new FooterBlock('core.logos', null, ['logos' => [['image' => 'footer/logos/a.png', 'alt' => 'Club', 'url' => '']]])]));
        DB::table('settings')->where('key', 'footer_layout')->update(['value' => '{not json']);

        $this->expectException(RuntimeException::class);

        $this->source()->usesFile(new MediaFileId('footer/logos/a'));
    }

    public function test_it_only_counts_blocks_of_the_right_kind(): void
    {
        $this->store(new FooterColumn([
            new FooterBlock('core.brand', null, ['logos' => [['image' => 'footer/logos/a.png', 'alt' => 'x']], 'content' => '<img src="'.self::BASE.'guildforge/events/b.png">']),
            new FooterBlock('example.logos', null, ['logos' => [['image' => 'footer/logos/c.png', 'alt' => 'x']]]),
        ]));

        $this->assertSame([], $this->usages());
    }

    public function test_the_edit_link_is_for_footer_managers_only(): void
    {
        $manager = $this->editorWithPermissions(['footer.manage']);
        $other = $this->editorWithPermissions(['events.view_any']);

        $this->assertSame(FooterSettings::getUrl(), $this->source()->editUrl('layout', $manager));
        $this->assertNull($this->source()->editUrl('layout', $other));
        $this->assertNull($this->source()->editUrl('otro', $manager));
    }
}
