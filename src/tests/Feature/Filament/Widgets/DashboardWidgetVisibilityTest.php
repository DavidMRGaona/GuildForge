<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Resources\ArticleResource;
use App\Filament\Widgets\GalleryStatsWidget;
use App\Filament\Widgets\RecentArticlesWidget;
use App\Filament\Widgets\UpcomingEventsWidget;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Widgets\Widget;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * Core dashboard widgets only show to whoever may list their content, so a narrow role
 * (treasury, venue…) gets a dashboard about its own area.
 */
final class DashboardWidgetVisibilityTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    /**
     * @param  class-string<Widget>  $widget
     */
    #[DataProvider('widgets')]
    public function test_widget_shows_with_its_view_any_permission(string $widget, string $permission): void
    {
        $this->actingAs($this->editorWithPermissions([$permission]));

        $this->assertTrue($widget::canView());
        $this->get('/admin')->assertOk()->assertSee($this->rendered($widget), false);
    }

    /**
     * @param  class-string<Widget>  $widget
     */
    #[DataProvider('widgets')]
    public function test_widget_hidden_without_its_view_any_permission(string $widget, string $permission): void
    {
        $others = array_values(array_diff(array_column(self::widgets(), 1), [$permission]));
        $this->actingAs($this->editorWithPermissions($others));

        $this->assertFalse($widget::canView());
        $this->get('/admin')->assertOk()->assertDontSee($this->rendered($widget), false);
    }

    /**
     * @param  class-string<Widget>  $widget
     */
    #[DataProvider('widgets')]
    public function test_admin_sees_widget(string $widget, string $permission): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->assertTrue($widget::canView());
    }

    public function test_recent_article_links_to_edit_only_for_who_may_edit(): void
    {
        $article = ArticleModel::factory()->published()->create();
        $editUrl = ArticleResource::getUrl('edit', ['record' => $article]);

        $this->actingAs($this->editorWithPermissions(['articles.view_any']));
        Livewire::test(RecentArticlesWidget::class)
            ->assertSee($article->title)
            ->assertDontSee($editUrl);

        $this->actingAs($this->editorWithPermissions(['articles.view_any', 'articles.update']));
        Livewire::test(RecentArticlesWidget::class)
            ->assertSee($editUrl);
    }

    /**
     * The dashboard renders each visible widget as a Livewire component named after its class.
     */
    private function rendered(string $widget): string
    {
        return 'wire:name="'.$widget.'"';
    }

    /**
     * @return array<string, array{class-string<Widget>, string}>
     */
    public static function widgets(): array
    {
        return [
            'RecentArticlesWidget' => [RecentArticlesWidget::class, 'articles.view_any'],
            'UpcomingEventsWidget' => [UpcomingEventsWidget::class, 'events.view_any'],
            'GalleryStatsWidget' => [GalleryStatsWidget::class, 'galleries.view_any'],
        ];
    }
}
