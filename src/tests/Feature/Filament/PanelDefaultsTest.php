<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Defaults the admin panel was built on: tables filter as soon as a filter changes and
 * offer every page size including "all", sections take the full width, and uploads to
 * the "images" disk (Cloudinary) are public.
 */
final class PanelDefaultsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_tables_apply_filters_at_once_and_offer_all_page_sizes(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $table = Livewire::test(ListArticles::class)->instance()->getTable();

        $this->assertFalse($table->hasDeferredFilters());
        $this->assertSame([5, 10, 25, 50, 'all'], $table->getPaginationPageOptions());
    }

    public function test_sections_span_the_full_width(): void
    {
        $this->assertSame('full', Section::make()->getColumnSpan()['default'] ?? null);
    }

    public function test_images_are_stored_and_shown_as_public(): void
    {
        $this->assertSame('public', FileUpload::make('image')->disk('images')->getVisibility());
        $this->assertSame('public', ImageColumn::make('image')->disk('images')->getVisibility());
    }
}
