<?php

declare(strict_types=1);

namespace Tests\Feature\Discussions;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The conversation of an event is drawn by a module in the `subject-discussion` slot, which the page
 * fills with the event's id: the core no longer sends a summary of its own.
 */
final class EventPageDiscussionSlotTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_event_page_sends_no_discussion_prop(): void
    {
        $event = EventModel::factory()->published()->upcoming()->create();

        $this->get('/eventos/'.$event->slug)->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Events/Show')->missing('discussion')->where('event.id', $event->id)
        );
    }

    public function test_the_event_page_offers_the_slot_with_the_event_as_subject(): void
    {
        $source = (string) file_get_contents(resource_path('js/pages/Events/Show.vue'));

        $this->assertStringContainsString('name="subject-discussion"', $source);
        $this->assertStringContainsString("subjectType: 'event'", $source);
        $this->assertStringContainsString('subjectId: props.event.id', $source);
        $this->assertStringNotContainsString('SubjectDiscussion', $source);
    }
}
