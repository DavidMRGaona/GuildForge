<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\CoreUpdatesPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ModulesPage;
use App\Filament\Pages\ModuleUpdatesPage;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Filament\Pages\Settings\MailSettings;
use App\Filament\Pages\Settings\MailStatisticsPage;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\GalleryResource;
use App\Filament\Resources\HeroSlideResource;
use App\Filament\Resources\MenuItemResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\TagResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\GalleryStatsWidget;
use App\Filament\Widgets\MailHealthWidget;
use App\Filament\Widgets\MailStatsOverviewWidget;
use App\Filament\Widgets\RecentArticlesWidget;
use App\Filament\Widgets\UpcomingEventsWidget;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every page, resource page and widget of the core admin panel renders for an admin.
 * Module resources, pages and widgets are covered by each module's own suite.
 */
final class AdminPanelRenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Update pages read GitHub only on demand; nothing here may reach the network
        Http::preventStrayRequests();
        Http::fake();
        $this->actingAs(UserModel::factory()->admin()->create());
    }

    /**
     * @param  class-string  $resource
     */
    #[DataProvider('resourcePages')]
    public function test_resource_page_renders(string $resource, string $page): void
    {
        $parameters = in_array($page, ['edit', 'view'], true) ? ['record' => $this->recordFor($resource)] : [];

        $this->get($resource::getUrl($page, $parameters))->assertOk();
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function resourcePages(): array
    {
        $cases = [];

        foreach ([ArticleResource::class, EventResource::class, GalleryResource::class, HeroSlideResource::class, MenuItemResource::class, RoleResource::class, TagResource::class, UserResource::class] as $resource) {
            foreach (['index', 'create', 'edit'] as $page) {
                $cases[class_basename($resource).' '.$page] = [$resource, $page];
            }
        }

        return $cases;
    }

    /**
     * @param  class-string  $page
     */
    #[DataProvider('customPages')]
    public function test_custom_page_renders(string $page): void
    {
        $this->get($page::getUrl())->assertOk();
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function customPages(): array
    {
        $pages = [Dashboard::class, CoreUpdatesPage::class, ModuleUpdatesPage::class, ModulesPage::class, SiteSettings::class, AboutPageSettings::class, DashboardSettings::class, LegalPagesSettings::class, MailSettings::class, MailStatisticsPage::class];

        return array_combine(array_map(class_basename(...), $pages), array_map(static fn (string $page): array => [$page], $pages));
    }

    /**
     * @param  class-string  $widget
     */
    #[DataProvider('widgets')]
    public function test_dashboard_widget_renders(string $widget): void
    {
        Livewire::test($widget)->assertOk();
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function widgets(): array
    {
        $widgets = [UpcomingEventsWidget::class, RecentArticlesWidget::class, GalleryStatsWidget::class, MailHealthWidget::class, MailStatsOverviewWidget::class];

        return array_combine(array_map(class_basename(...), $widgets), array_map(static fn (string $widget): array => [$widget], $widgets));
    }

    /**
     * @param  class-string  $resource
     */
    private function recordFor(string $resource): Model
    {
        /** @var class-string<Model> $model */
        $model = $resource::getModel();

        if ($model === RoleModel::class) {
            return RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);
        }

        return $model::factory()->create();
    }
}
