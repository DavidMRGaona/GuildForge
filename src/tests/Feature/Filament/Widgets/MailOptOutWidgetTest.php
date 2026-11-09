<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Filament\Pages\Settings\MailStatisticsPage;
use App\Filament\Widgets\MailOptOutWidget;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class MailOptOutWidgetTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->app->make(MailCategoryRegistryInterface::class)->register(MailCategoryDTO::optional(
            key: 'test.news',
            section: 'Sección de prueba',
            label: 'Novedades de prueba',
            description: 'Novedades',
        ), 'test');
    }

    public function test_it_shows_the_counts_per_category_and_the_paused_accounts_without_naming_anyone(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $quiet = UserModel::factory()->create(['email' => 'quiet.person@example.test']);
        UserModel::factory()->create();
        DB::table('user_mail_preferences')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $quiet->id, 'category' => 'test.news',
            'enabled' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_mail_pauses')->insert([
            'user_id' => $quiet->id, 'paused_until' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(MailOptOutWidget::class)
            ->assertSee('Novedades de prueba')
            ->assertSee('33,3 %')
            ->assertSee('1 cuenta')
            ->assertDontSee('quiet.person@example.test')
            ->assertDontSee($quiet->name);
    }

    public function test_the_page_shows_the_section_to_who_can_see_statistics(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(MailStatisticsPage::getUrl())
            ->assertOk()
            ->assertSee('Bajas por categoría de correo');
    }

    public function test_it_is_hidden_without_the_permission(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->assertFalse(MailOptOutWidget::canView());
    }

    public function test_it_is_visible_with_the_permission(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->assertTrue(MailOptOutWidget::canView());
    }
}
