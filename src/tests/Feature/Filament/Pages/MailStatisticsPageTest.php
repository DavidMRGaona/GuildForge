<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Mail\DTOs\Response\MailStatisticsResponseDTO;
use App\Application\Mail\Services\MailStatisticsServiceInterface;
use App\Domain\Mail\Enums\EmailStatus;
use App\Filament\Pages\Settings\MailStatisticsPage;
use App\Infrastructure\Persistence\Eloquent\Models\EmailLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class MailStatisticsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_mail_statistics_page_requires_admin(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user);

        $this->get(MailStatisticsPage::getUrl())
            ->assertForbidden();
    }

    public function test_mail_statistics_page_accessible_by_admin(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        $this->get(MailStatisticsPage::getUrl())
            ->assertOk();
    }

    public function test_mail_statistics_page_displays_stats(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        $this->mock(MailStatisticsServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('getStats')
                ->once()
                ->andReturn(new MailStatisticsResponseDTO(
                    sentToday: 42,
                    sentThisMonth: 350,
                    failedToday: 3,
                    failedThisMonth: 15,
                    deliveryRate: 93.3,
                ));
        });

        Livewire::test(MailStatisticsPage::class)
            ->assertSee('42')
            ->assertSee('350')
            ->assertSee('3')
            ->assertSee('15')
            ->assertSee('93.3');
    }

    public function test_mail_statistics_page_shows_recent_failed_emails_table(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        EmailLogModel::factory()
            ->count(3)
            ->create([
                'status' => EmailStatus::Failed,
                'error_message' => 'Connection timeout',
            ]);

        Livewire::test(MailStatisticsPage::class)
            ->assertSee('Connection timeout');
    }

    public function test_mail_statistics_page_shows_delivery_rate_with_zero_emails(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        $this->mock(MailStatisticsServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('getStats')
                ->once()
                ->andReturn(new MailStatisticsResponseDTO(
                    sentToday: 0,
                    sentThisMonth: 0,
                    failedToday: 0,
                    failedThisMonth: 0,
                    deliveryRate: 100.0,
                ));
        });

        Livewire::test(MailStatisticsPage::class)
            ->assertSee('100');
    }
}
