<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Filament\Widgets\PendingProposalsWidget;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * «Propuestas pendientes» on the dashboard (core#575): only for whoever may review a type, only
 * while something of that type awaits review.
 */
final class PendingProposalsWidgetTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_hidden_without_a_review_permission(): void
    {
        EventModel::factory()->proposal(ProposalStatus::PendingReview)->create();
        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update']));

        $this->assertFalse(PendingProposalsWidget::canView());
    }

    public function test_hidden_while_nothing_the_viewer_reviews_is_pending(): void
    {
        EventModel::factory()->proposal(ProposalStatus::Rejected)->create();
        EventModel::factory()->proposal(ProposalStatus::Draft, submitted: true)->create();
        ArticleModel::factory()->proposal(ProposalStatus::PendingReview)->create();
        $this->actingAs($this->editorWithPermissions(['events.review']));

        $this->assertFalse(PendingProposalsWidget::canView());
    }

    public function test_lists_the_pending_proposals_of_the_types_the_viewer_reviews(): void
    {
        $author = UserModel::factory()->create(['display_name' => 'Persona que propone']);
        EventModel::factory()->proposal(ProposalStatus::PendingReview, $author)->create(['title' => 'Jornada de rol']);
        EventModel::factory()->proposal(ProposalStatus::Rejected)->create(['title' => 'Evento rechazado']);
        ArticleModel::factory()->proposal(ProposalStatus::PendingReview)->create(['title' => 'Crónica del torneo']);
        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update', 'events.review']));

        $this->assertTrue(PendingProposalsWidget::canView());
        Livewire::test(PendingProposalsWidget::class)
            ->assertSee('Jornada de rol')
            ->assertSee('Persona que propone')
            ->assertDontSee('Evento rechazado')
            ->assertDontSee('Crónica del torneo');
    }

    public function test_an_admin_sees_both_types(): void
    {
        EventModel::factory()->proposal(ProposalStatus::PendingReview)->create(['title' => 'Jornada de rol']);
        ArticleModel::factory()->proposal(ProposalStatus::PendingReview)->create(['title' => 'Crónica del torneo']);
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->assertTrue(PendingProposalsWidget::canView());
        Livewire::test(PendingProposalsWidget::class)
            ->assertSee('Jornada de rol')
            ->assertSee('Crónica del torneo');
        $this->get('/admin')->assertOk()->assertSee('wire:name="'.PendingProposalsWidget::class.'"', false);
    }

    public function test_shows_as_many_rows_as_configured_oldest_first(): void
    {
        EventModel::factory()->proposal(ProposalStatus::PendingReview)->create(['title' => 'Primera', 'submitted_at' => now()->subDays(3)]);
        EventModel::factory()->proposal(ProposalStatus::PendingReview)->create(['title' => 'Segunda', 'submitted_at' => now()->subDays(2)]);
        EventModel::factory()->proposal(ProposalStatus::PendingReview)->create(['title' => 'Tercera', 'submitted_at' => now()->subDay()]);
        app(DashboardWidgetConfigServiceInterface::class)->saveConfig([
            PendingProposalsWidget::class => ['enabled' => true, 'sort' => 10, 'limit' => 2],
        ]);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(PendingProposalsWidget::class)
            ->assertSeeInOrder(['Primera', 'Segunda'])
            ->assertDontSee('Tercera');
    }

    public function test_the_dashboard_settings_name_it(): void
    {
        $this->assertSame('Propuestas pendientes', __('filament.dashboard_settings.widgets.pending_proposals_widget'));
    }
}
