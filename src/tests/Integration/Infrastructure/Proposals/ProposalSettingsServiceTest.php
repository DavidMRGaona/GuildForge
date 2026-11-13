<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Proposals;

use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalAccessLevel;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ProposalSettingsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): ProposalSettingsServiceInterface
    {
        return app(ProposalSettingsServiceInterface::class);
    }

    public function test_proposals_are_off_until_turned_on(): void
    {
        $settings = $this->service()->settings();

        $this->assertFalse($settings->events->enabled);
        $this->assertFalse($settings->articles->enabled);
        $this->assertSame(ProposalAccessLevel::Registered, $settings->events->accessLevel);
        $this->assertFalse($settings->events->showProposer);
        $this->assertSame(3, $settings->maxPending);
        $this->assertSame(5, $settings->maxOpenDrafts);
        $this->assertSame(90, $settings->rejectedRetentionDays);
        $this->assertSame(5, $settings->submissionsPerHour);
    }

    public function test_it_saves_and_reads_back_every_setting(): void
    {
        RoleModel::query()->create(['name' => 'tesoreria', 'display_name' => 'Tesorería']);

        $this->service()->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(true, ProposalAccessLevel::Roles, ['tesoreria'], true),
            articles: new ProposalTypeSettingsDTO(true, ProposalAccessLevel::Permission),
            maxPending: 4,
            maxOpenDrafts: 6,
            rejectedRetentionDays: 30,
            submissionsPerHour: 2,
        ));

        $settings = $this->service()->settings();
        $this->assertTrue($settings->forType(ProposalType::Event)->enabled);
        $this->assertSame(ProposalAccessLevel::Roles, $settings->events->accessLevel);
        $this->assertSame(['tesoreria'], $settings->events->roles);
        $this->assertTrue($settings->events->showProposer);
        $this->assertSame(ProposalAccessLevel::Permission, $settings->forType(ProposalType::Article)->accessLevel);
        $this->assertFalse($settings->articles->showProposer, 'articles show their author anyway');
        $this->assertSame([4, 6, 30, 2], [$settings->maxPending, $settings->maxOpenDrafts, $settings->rejectedRetentionDays, $settings->submissionsPerHour]);
    }

    public function test_unknown_roles_are_dropped_and_numbers_clamped_when_saving(): void
    {
        RoleModel::query()->create(['name' => 'tesoreria', 'display_name' => 'Tesorería']);

        $this->service()->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(true, ProposalAccessLevel::Roles, ['tesoreria', 'nadie']),
            maxPending: 0,
            maxOpenDrafts: 1000,
            rejectedRetentionDays: -5,
            submissionsPerHour: 500,
        ));

        $settings = $this->service()->settings();
        $this->assertSame(['tesoreria'], $settings->events->roles);
        $this->assertSame(1, $settings->maxPending);
        $this->assertSame(100, $settings->maxOpenDrafts);
        $this->assertSame(1, $settings->rejectedRetentionDays);
        $this->assertSame(100, $settings->submissionsPerHour);
    }

    public function test_reading_fails_closed_on_unreadable_values(): void
    {
        $store = app(SettingsServiceInterface::class);
        $store->set('proposals_events_enabled', 'tal vez');
        $store->set('proposals_articles_enabled', '1');
        $store->set('proposals_articles_access_level', 'everyone');
        $store->set('proposals_events_roles', '{not json');
        $store->set('proposals_max_pending', 'muchas');

        $settings = $this->service()->settings();

        $this->assertFalse($settings->events->enabled);
        $this->assertSame([], $settings->events->roles);
        $this->assertTrue($settings->articles->enabled);
        $this->assertNull($settings->articles->accessLevel, 'an unknown level lets nobody propose');
        $this->assertSame(3, $settings->maxPending);
    }
}
