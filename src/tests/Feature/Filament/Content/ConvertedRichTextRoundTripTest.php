<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Content;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Content\Services\TrixHtmlConverter;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\Infrastructure\Content\Services\TrixHtmlConverterTest;
use Tests\TestCase;

/**
 * Converted content is what the panel's editor stores: saving it again without touching
 * the editor must not change a byte (the record and the settings form paths).
 */
final class ConvertedRichTextRoundTripTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    #[DataProvider('convertedHtml')]
    public function test_saving_a_converted_event_description_keeps_it(string $trix): void
    {
        $converted = (new TrixHtmlConverter())->convert($trix);
        $event = EventModel::factory()->create([
            'description' => $converted,
            'start_date' => now()->addDays(10)->startOfHour(),
            'end_date' => now()->addDays(10)->startOfHour()->addHours(4),
        ]);
        $event->tags()->attach(TagModel::factory()->forEvents()->create(['parent_id' => null])->id);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($converted, $event->fresh()?->description);
    }

    #[DataProvider('convertedHtml')]
    public function test_saving_a_converted_legal_page_keeps_it(string $trix): void
    {
        $converted = (new TrixHtmlConverter())->convert($trix);
        $settings = app(SettingsServiceInterface::class);
        $settings->set('legal_privacy_content', $converted);

        Livewire::test(LegalPagesSettings::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($converted, $settings->get('legal_privacy_content'));
    }

    public function test_saving_the_converted_real_article_keeps_it(): void
    {
        $converted = (new TrixHtmlConverter())->convert((string) file_get_contents(base_path('tests/Fixtures/rich-text/trix-article.html')));
        $article = ArticleModel::factory()->create(['content' => $converted]);
        $article->tags()->attach(TagModel::factory()->forArticles()->create(['parent_id' => null])->id);

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($converted, $article->fresh()?->content);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function convertedHtml(): array
    {
        return array_map(static fn (array $case): array => [$case[0]], TrixHtmlConverterTest::trixHtml());
    }
}
