<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Linking\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
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

    public function test_a_rejected_proposal_is_found_only_by_who_lists_events_in_the_panel(): void
    {
        $rejected = EventModel::factory()->proposal(ProposalStatus::Rejected)->upcoming()->create();

        $this->assertNull($this->source()->find($rejected->id, UserModel::factory()->create()->id));
        $this->assertSame($rejected->id, $this->source()->find($rejected->id, $this->editorWithPermissions(['events.view_any'])->id)?->id);
    }

    public function test_a_deactivated_viewer_sees_only_what_a_visitor_would(): void
    {
        $published = EventModel::factory()->published()->upcoming()->create(['title' => 'Example published']);
        $draft = EventModel::factory()->draft()->upcoming()->create(['title' => 'Example draft']);
        $viewer = $this->editorWithPermissions(['events.view_any', 'events.update']);
        $viewer->delete();

        $this->assertNull($this->source()->find($draft->id, $viewer->id));
        $this->assertNull($this->source()->find($published->id, $viewer->id)?->panelUrl);
        $this->assertSame([$published->id], $this->ids($this->source()->search('example', $viewer->id, 10)));
        $this->assertSame([$published->id], $this->ids($this->source()->findMany([$published->id, $draft->id], $viewer->id)));
    }

    public function test_an_anonymized_viewer_sees_only_what_a_visitor_would(): void
    {
        $published = EventModel::factory()->published()->upcoming()->create(['title' => 'Example published']);
        $draft = EventModel::factory()->draft()->upcoming()->create(['title' => 'Example draft']);
        $viewer = $this->editorWithPermissions(['events.view_any', 'events.update']);
        $viewer->forceFill(['anonymized_at' => now()])->save();

        $this->assertNull($this->source()->find($draft->id, $viewer->id));
        $this->assertNull($this->source()->find($published->id, $viewer->id)?->panelUrl);
        $this->assertSame([$published->id], $this->ids($this->source()->search('example', $viewer->id, 10)));
        $this->assertSame([$published->id], $this->ids($this->source()->findMany([$published->id, $draft->id], $viewer->id)));
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

    public function test_search_gives_the_panel_url_to_who_may_edit_the_event_in_the_panel(): void
    {
        $event = EventModel::factory()->published()->upcoming()->create(['title' => 'Example event']);

        $editor = $this->editorWithPermissions(['events.view_any', 'events.update']);
        $this->assertSame([EventResource::getUrl('edit', ['record' => $event])], $this->panelUrls($this->source()->search('example', $editor->id, 10)));

        $readOnly = $this->editorWithPermissions(['events.view_any']);
        $this->assertSame([null], $this->panelUrls($this->source()->search('example', $readOnly->id, 10)));

        $withoutPanel = $this->memberWithPermissions(['events.view_any', 'events.update']);
        $this->assertSame([null], $this->panelUrls($this->source()->search('example', $withoutPanel->id, 10)));
    }

    public function test_find_many_reads_every_visible_event_in_one_query(): void
    {
        $published = EventModel::factory()->published()->upcoming()->create();
        $draft = EventModel::factory()->draft()->upcoming()->create();
        $other = EventModel::factory()->published()->upcoming()->create();
        $editor = $this->editorWithPermissions(['events.view_any', 'events.update']);
        $member = UserModel::factory()->create();

        $eventQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$eventQueries): void {
            if (preg_match('/from\s+["`]?events["`]?/i', $query->sql) === 1) {
                $eventQueries++;
            }
        });

        $records = $this->source()->findMany([$published->id, $draft->id, (string) Str::uuid(), 'not-a-uuid', $other->id], $editor->id);

        $this->assertSame(1, $eventQueries);
        $this->assertEqualsCanonicalizing([$published->id, $draft->id, $other->id], $this->ids($records));
        $this->assertContains(EventResource::getUrl('edit', ['record' => $draft]), $this->panelUrls($records));

        $this->assertEqualsCanonicalizing([$published->id, $other->id], $this->ids($this->source()->findMany([$published->id, $draft->id, $other->id], $member->id)));
        $this->assertSame([], $this->source()->findMany(['not-a-uuid'], $member->id));
    }

    public function test_the_lookup_asks_the_event_source_in_one_batch(): void
    {
        $first = EventModel::factory()->published()->upcoming()->create();
        $second = EventModel::factory()->published()->upcoming()->create();
        $member = UserModel::factory()->create();

        $records = $this->app->make(LinkableRecordLookupInterface::class)->findMany('event', [$second->id, $first->id], $member->id);

        $this->assertSame([$second->id, $first->id], array_keys($records));
    }

    public function test_the_lookup_shows_no_drafts_to_who_lists_events_without_panel_access(): void
    {
        $published = EventModel::factory()->published()->upcoming()->create(['title' => 'Example published']);
        $draft = EventModel::factory()->draft()->upcoming()->create(['title' => 'Example draft']);
        $viewer = $this->memberWithPermissions(['events.view_any']);

        // The source alone would show the draft: the lookup asks it as a visitor
        $this->assertSame($draft->id, $this->source()->find($draft->id, $viewer->id)?->id);

        $this->assertNull($this->lookup()->find('event', $draft->id, $viewer->id));
        $this->assertSame($published->id, $this->lookup()->find('event', $published->id, $viewer->id)?->id);
        $this->assertSame([$published->id], array_keys($this->lookup()->findMany('event', [$draft->id, $published->id], $viewer->id)));
        $this->assertSame([$published->id], $this->ids($this->lookup()->search('event', 'example', $viewer->id)));
    }

    public function test_the_lookup_shows_drafts_to_who_lists_events_in_the_panel(): void
    {
        $published = EventModel::factory()->published()->upcoming()->create(['title' => 'Example published']);
        $draft = EventModel::factory()->draft()->upcoming()->create(['title' => 'Example draft']);
        $viewer = $this->editorWithPermissions(['events.view_any', 'events.update']);

        $this->assertSame($draft->id, $this->lookup()->find('event', $draft->id, $viewer->id)?->id);
        $this->assertSame([$draft->id, $published->id], array_keys($this->lookup()->findMany('event', [$draft->id, $published->id], $viewer->id)));
        $this->assertEqualsCanonicalizing([$published->id, $draft->id], $this->ids($this->lookup()->search('event', 'example', $viewer->id)));
        $this->assertSame(EventResource::getUrl('edit', ['record' => $draft]), $this->lookup()->find('event', $draft->id, $viewer->id)?->panelUrl);
    }

    public function test_the_lookup_treats_a_failing_panel_check_as_a_visitor(): void
    {
        $published = EventModel::factory()->published()->upcoming()->create(['title' => 'Example published']);
        $draft = EventModel::factory()->draft()->upcoming()->create(['title' => 'Example draft']);
        $viewer = $this->editorWithPermissions(['events.view_any', 'events.update']);
        $log = Log::spy();
        $authorization = Mockery::mock(AuthorizationServiceInterface::class);
        $authorization->shouldReceive('can')->andThrow(new RuntimeException('Authorization unavailable'));
        $this->app->instance(AuthorizationServiceInterface::class, $authorization);
        $this->app->forgetInstance(LinkableRecordLookupInterface::class);
        $this->app->forgetInstance(EventLinkableRecordSource::class);

        // A visitor is never checked by the source, so what is published is still shown
        $this->assertNull($this->lookup()->find('event', $draft->id, $viewer->id));
        $this->assertSame([$published->id], array_keys($this->lookup()->findMany('event', [$draft->id, $published->id], $viewer->id)));
        $this->assertSame([$published->id], $this->ids($this->lookup()->search('event', 'example', $viewer->id)));
        $this->assertNull($this->lookup()->find('event', $published->id, $viewer->id)?->panelUrl);
        $log->shouldHaveReceived('error')->times(4);
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

    private function lookup(): LinkableRecordLookupInterface
    {
        return $this->app->make(LinkableRecordLookupInterface::class);
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
     * @return list<string|null>
     */
    private function panelUrls(array $records): array
    {
        return array_map(fn (LinkableRecordDTO $record): ?string => $record->panelUrl, $records);
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
