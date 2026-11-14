<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Linking\Sources;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Filament\Linking\Sources\EventLinkableRecordSource;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class EventLinkableRecordSourceTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_the_core_registers_its_event_source(): void
    {
        $source = $this->app->make(LinkableRecordSourceRegistryInterface::class)->source('event');

        $this->assertInstanceOf(EventLinkableRecordSource::class, $source);
        $this->assertSame('Evento', $source->label());
        $this->assertSame(['event' => 'Evento'], $this->app->make(LinkableRecordLookupInterface::class)->types());
    }

    public function test_a_published_event_is_found_for_anyone_with_its_start_as_date(): void
    {
        $event = EventModel::factory()->published()->upcoming()->create(['title' => 'Example event']);
        $member = UserModel::factory()->create();

        $record = $this->source()->find($event->id, $member->id);

        $this->assertNotNull($record);
        $this->assertSame('event', $record->type);
        $this->assertSame($event->id, $record->id);
        $this->assertSame('Example event', $record->title);
        $this->assertSame($event->start_date->getTimestamp(), $record->date?->getTimestamp());
        $this->assertFalse($record->dateIsDay);
        $this->assertNull($record->panelUrl);
    }

    public function test_a_draft_is_found_only_by_who_lists_events_in_the_panel(): void
    {
        $draft = EventModel::factory()->draft()->upcoming()->create();

        $this->assertNull($this->source()->find($draft->id, UserModel::factory()->create()->id));
        $this->assertNull($this->source()->find($draft->id, $this->editorWithPermissions([])->id));
        $this->assertSame($draft->id, $this->source()->find($draft->id, $this->editorWithPermissions(['events.view_any'])->id)?->id);
    }

    public function test_an_unknown_viewer_sees_only_published_events(): void
    {
        $published = EventModel::factory()->published()->upcoming()->create();
        $draft = EventModel::factory()->draft()->upcoming()->create();
        $nobody = (string) Str::uuid();

        $this->assertSame($published->id, $this->source()->find($published->id, $nobody)?->id);
        $this->assertNull($this->source()->find($draft->id, $nobody));
        $this->assertNull($this->source()->find($draft->id, 'not-a-uuid'));
    }

    public function test_proposals_follow_the_panel(): void
    {
        $viewer = $this->editorWithPermissions(['events.view_any']);
        $unsubmitted = EventModel::factory()->proposal(ProposalStatus::Draft)->upcoming()->create();
        $inReview = EventModel::factory()->proposal(ProposalStatus::PendingReview)->upcoming()->create();

        // Drafts never submitted are hidden from the panel too
        $this->assertNull($this->source()->find($unsubmitted->id, $viewer->id));
        $this->assertSame($inReview->id, $this->source()->find($inReview->id, $viewer->id)?->id);
    }

    public function test_unknown_and_malformed_ids_are_not_found(): void
    {
        $viewer = $this->editorWithPermissions(['events.view_any']);

        $this->assertNull($this->source()->find((string) Str::uuid(), $viewer->id));
        $this->assertNull($this->source()->find('not-a-uuid', $viewer->id));
    }

    public function test_the_panel_url_is_given_to_who_may_edit_the_event_in_the_panel(): void
    {
        $event = EventModel::factory()->published()->upcoming()->create();

        $editor = $this->editorWithPermissions(['events.view_any', 'events.update']);
        $this->assertSame(EventResource::getUrl('edit', ['record' => $event]), $this->source()->find($event->id, $editor->id)?->panelUrl);

        $readOnly = $this->editorWithPermissions(['events.view_any']);
        $this->assertNull($this->source()->find($event->id, $readOnly->id)?->panelUrl);

        $withoutPanel = $this->memberWithPermissions(['events.view_any', 'events.update']);
        $this->assertNull($this->source()->find($event->id, $withoutPanel->id)?->panelUrl);
    }

    public function test_search_matches_every_word_of_the_title_ignoring_case(): void
    {
        $match = EventModel::factory()->published()->upcoming()->create(['title' => 'Autumn Painting Workshop']);
        EventModel::factory()->published()->upcoming()->create(['title' => 'Autumn tournament']);
        $member = UserModel::factory()->create();

        $records = $this->source()->search('  workshop   AUTUMN ', $member->id, 10);

        $this->assertSame([$match->id], $this->ids($records));
    }

    public function test_search_leaves_drafts_to_who_lists_events_in_the_panel(): void
    {
        $published = EventModel::factory()->published()->upcoming()->create(['title' => 'Example published']);
        $draft = EventModel::factory()->draft()->upcoming()->create(['title' => 'Example draft']);

        $this->assertSame([$published->id], $this->ids($this->source()->search('example', UserModel::factory()->create()->id, 10)));

        $viewer = $this->editorWithPermissions(['events.view_any']);
        $this->assertEqualsCanonicalizing([$published->id, $draft->id], $this->ids($this->source()->search('example', $viewer->id, 10)));
    }

    public function test_search_takes_wildcards_literally(): void
    {
        $match = EventModel::factory()->published()->upcoming()->create(['title' => 'Discount 100% off']);
        EventModel::factory()->published()->upcoming()->create(['title' => 'Discount 1000 off']);
        EventModel::factory()->published()->upcoming()->create(['title' => 'Discount 10_0 off']);
        $member = UserModel::factory()->create();

        $this->assertSame([$match->id], $this->ids($this->source()->search('100%', $member->id, 10)));
        $this->assertSame([], $this->ids($this->source()->search('1_0', $member->id, 10)));
    }

    public function test_search_lists_the_latest_events_first_up_to_the_limit(): void
    {
        $older = EventModel::factory()->published()->create(['start_date' => now()->subMonth(), 'end_date' => now()->subMonth()->addHours(2)]);
        $newest = EventModel::factory()->published()->create(['start_date' => now()->addMonth(), 'end_date' => now()->addMonth()->addHours(2)]);
        $middle = EventModel::factory()->published()->create(['start_date' => now()->addWeek(), 'end_date' => now()->addWeek()->addHours(2)]);
        $member = UserModel::factory()->create();

        $this->assertSame([$newest->id, $middle->id, $older->id], $this->ids($this->source()->search('', $member->id, 10)));
        $this->assertSame([$newest->id, $middle->id], $this->ids($this->source()->search('', $member->id, 2)));
        $this->assertSame([], $this->source()->search('', $member->id, 0));
    }

    private function source(): EventLinkableRecordSource
    {
        return $this->app->make(EventLinkableRecordSource::class);
    }

    /**
     * An account holding the given permissions without panel access.
     *
     * @param  list<string>  $permissionKeys
     */
    private function memberWithPermissions(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester-'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::firstOrCreate(['key' => $key], ['label' => $key, 'resource' => $resource, 'action' => $action]);
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * @param  list<LinkableRecordDTO>  $records
     * @return list<string>
     */
    private function ids(array $records): array
    {
        return array_map(fn (LinkableRecordDTO $record): string => $record->id, $records);
    }
}
