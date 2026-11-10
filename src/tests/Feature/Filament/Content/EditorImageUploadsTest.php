<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Content;

use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\KnowledgeGuideResource\Pages\CreateKnowledgeGuide;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Images inserted with the panel's rich editors go to the "images" disk (Cloudinary), in a
 * content folder of their origin and month, with a UUID name (core#481).
 */
final class EditorImageUploadsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 12:00:00');
        $this->actingAs(UserModel::factory()->admin()->create());
    }

    /**
     * @return array<string, array{class-string, string, string}>
     */
    public static function editors(): array
    {
        return [
            'event description' => [CreateEvent::class, 'description', 'events'],
            'article content' => [CreateArticle::class, 'content', 'articles'],
            'guide body' => [CreateKnowledgeGuide::class, 'body', 'knowledge'],
            'privacy policy' => [LegalPagesSettings::class, 'legal_privacy_content', 'legal'],
            'legal notice' => [LegalPagesSettings::class, 'legal_notice_content', 'legal'],
            'cookie policy' => [LegalPagesSettings::class, 'legal_cookies_content', 'legal'],
            'terms' => [LegalPagesSettings::class, 'legal_terms_content', 'legal'],
            'about history' => [AboutPageSettings::class, 'about_history', 'about'],
        ];
    }

    /**
     * @param  class-string  $page
     */
    #[DataProvider('editors')]
    public function test_the_editor_uploads_images_to_cloudinary(string $page, string $field, string $origin): void
    {
        $editor = $this->editor($page, $field);

        $this->assertTrue($editor->hasFileAttachments());
        $this->assertSame('images', $editor->getFileAttachmentsDiskName());
        $this->assertSame($origin.'/content/2026/11', $editor->getFileAttachmentsDirectory());
        $this->assertSame('public', $editor->getFileAttachmentsVisibility());
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp', 'image/gif'], $editor->getFileAttachmentsAcceptedFileTypes());
        $this->assertSame(2048, $editor->getFileAttachmentsMaxSize());
    }

    /**
     * @param  class-string  $page
     */
    #[DataProvider('editors')]
    public function test_an_upload_is_stored_with_a_uuid_name_on_the_images_disk(string $page, string $field, string $origin): void
    {
        Storage::fake('images');
        Storage::fake('public');

        $path = $this->editor($page, $field)->saveUploadedFileAttachment($this->upload('Foto del local.png'));

        $this->assertIsString($path);
        $this->assertMatchesRegularExpression('~^'.$origin.'/content/2026/11/'.self::UUID.'\.png$~', $path);
        Storage::disk('images')->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_image_url_is_the_full_cloudinary_delivery_url_built_without_the_admin_api(): void
    {
        $editor = $this->editor(CreateArticle::class, 'content');

        $url = $editor->getFileAttachmentUrl('articles/content/2026/11/0b7e7c1e-6a0f-4c56-9d0e-1b2f3a4c5d6e.png');

        $this->assertSame(Storage::disk('images')->url('articles/content/2026/11/0b7e7c1e-6a0f-4c56-9d0e-1b2f3a4c5d6e.png'), $url);
        $this->assertStringStartsWith('https://res.cloudinary.com/', (string) $url);
        $this->assertStringContainsString('/guildforge/articles/content/2026/11/0b7e7c1e-6a0f-4c56-9d0e-1b2f3a4c5d6e', (string) $url);
    }

    public function test_an_image_still_on_the_local_disk_keeps_its_stored_url(): void
    {
        $editor = $this->editor(CreateArticle::class, 'content');

        // null makes Filament keep the src saved in the HTML until the images are moved
        $this->assertNull($editor->getFileAttachmentUrl('3fJk2Lm9Qx.png'));
        $this->assertNull($editor->getFileAttachmentUrl('articles/2026/11/featured.png'));
    }

    public function test_saved_html_holds_the_cloudinary_url(): void
    {
        $article = ArticleModel::factory()->create();
        $article->tags()->attach(TagModel::factory()->forArticles()->create(['parent_id' => null])->id);
        $path = 'articles/content/2026/11/0b7e7c1e-6a0f-4c56-9d0e-1b2f3a4c5d6e.png';

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['content' => '<p>Texto</p><p><img src="blob:temporal" data-id="'.$path.'" alt="Mesa"></p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $content = (string) $article->refresh()->content;
        $this->assertStringContainsString('src="'.Storage::disk('images')->url($path).'"', $content);
        $this->assertStringContainsString('data-id="'.$path.'"', $content);
    }

    public function test_the_guide_editor_keeps_its_toolbar(): void
    {
        $editor = $this->editor(CreateKnowledgeGuide::class, 'body');

        $buttons = array_merge(...array_map(static fn (mixed $group): array => (array) $group, $editor->getToolbarButtons()));

        // Images reach this editor only by pasting or dropping them
        $this->assertNotContains('attachFiles', $buttons);
    }

    /**
     * @param  class-string  $page
     */
    private function editor(string $page, string $field): RichEditor
    {
        $instance = Livewire::test($page)->instance();
        $this->assertInstanceOf(HasSchemas::class, $instance);
        $form = $instance->getSchema('form');
        $this->assertNotNull($form);
        $editor = $form->getFlatFields(withHidden: true)[$field] ?? null;
        $this->assertInstanceOf(RichEditor::class, $editor);

        return $editor;
    }

    private function upload(string $name): TemporaryUploadedFile
    {
        Storage::fake('tmp-for-tests');
        $stored = 'upload-'.bin2hex(random_bytes(4)).'.png';
        Storage::disk('tmp-for-tests')->putFileAs('livewire-tmp', UploadedFile::fake()->image($name, 8, 8), $stored);

        return new TemporaryUploadedFile($stored, 'tmp-for-tests');
    }
}
