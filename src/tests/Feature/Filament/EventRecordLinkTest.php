<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\ProposalToReviewNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * The panel finds an event by its slug (EventModel::getRouteKeyName()), but links stored in mails
 * and in the bell carry its id, which never changes when the title does (core#652).
 */
final class EventRecordLinkTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_the_edit_page_opens_from_the_events_id(): void
    {
        $this->actingAs($this->editor());
        $event = $this->event(ProposalStatus::PendingReview);

        $this->get(EventResource::getUrl('edit', ['record' => $event->id]))->assertOk();
    }

    public function test_the_review_notice_of_an_event_proposal_opens_its_edit_page(): void
    {
        $reviewer = $this->editor();
        $this->actingAs($reviewer);
        $event = $this->event(ProposalStatus::PendingReview);
        $notice = new ProposalToReviewNotification(ProposalType::Event, $event->id, $event->title, 'Persona que propone', (string) Str::uuid7());

        $this->get($notice->toSiteNotification($reviewer)->url)->assertOk();
    }

    public function test_the_edit_page_still_opens_from_its_slug(): void
    {
        $this->actingAs($this->editor());
        $event = $this->event();

        $this->get(EventResource::getUrl('edit', ['record' => $event]))->assertOk();
    }

    public function test_a_link_by_id_survives_a_new_title(): void
    {
        $this->actingAs($this->editor());
        $event = $this->event(ProposalStatus::PendingReview);
        $link = EventResource::getUrl('edit', ['record' => $event->id]);
        $oldSlug = $event->slug;

        $event->update(['title' => 'Otro título del evento']);
        $this->assertNotSame($oldSlug, $event->refresh()->slug);

        $this->get($link)->assertOk();
    }

    public function test_an_unknown_id_is_not_found(): void
    {
        $this->actingAs($this->editor());

        $this->get(EventResource::getUrl('edit', ['record' => (string) Str::uuid7()]))->assertNotFound();
    }

    public function test_a_draft_never_submitted_stays_out_of_the_panel_by_id_too(): void
    {
        $this->actingAs($this->editor());
        $draft = $this->event(ProposalStatus::Draft);

        $this->get(EventResource::getUrl('edit', ['record' => $draft->id]))->assertNotFound();
    }

    private function editor(): UserModel
    {
        return $this->editorWithPermissions(['events.view_any', 'events.view', 'events.update', 'events.review']);
    }

    private function event(?ProposalStatus $status = null): EventModel
    {
        $factory = EventModel::factory();

        if ($status instanceof ProposalStatus) {
            $factory = $factory->proposal($status);
        }

        $event = $factory->create([
            'title' => 'Club de lectura',
            'start_date' => now()->addDays(10)->setTime(10, 0),
            'end_date' => now()->addDays(10)->setTime(20, 0),
        ]);
        $event->tags()->attach(TagModel::factory()->forEvents()->create());

        return $event;
    }
}
