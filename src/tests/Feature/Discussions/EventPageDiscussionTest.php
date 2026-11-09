<?php

declare(strict_types=1);

namespace Tests\Feature\Discussions;

use App\Application\Discussions\Contracts\SubjectDiscussionInterface;
use App\Application\Discussions\DTOs\DiscussionActivityDTO;
use App\Application\Discussions\DTOs\SubjectDiscussionDTO;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * The event page shows the activity of its forum discussion when a forum module offers one, and
 * shows the same without the block when none does or it fails.
 */
final class EventPageDiscussionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_without_a_forum_module_there_is_no_discussion(): void
    {
        $this->assertNull($this->app->make(SubjectDiscussionInterface::class)->forSubject('event', 'event-1', null));
        $event = EventModel::factory()->published()->upcoming()->create();

        $this->get('/eventos/'.$event->slug)->assertInertia(
            fn (Assert $page) => $page->component('Events/Show')->where('discussion', null)
        );
    }

    public function test_a_forum_module_gives_the_page_the_discussion_of_the_event_for_whoever_looks(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->upcoming()->create();
        $this->app->instance(SubjectDiscussionInterface::class, new class implements SubjectDiscussionInterface
        {
            public function forSubject(string $type, string $id, ?string $userId): SubjectDiscussionDTO
            {
                return new SubjectDiscussionDTO(new DiscussionActivityDTO(
                    url: "https://forum.example.org/d/{$type}/{$id}",
                    actionUrl: 'https://web.example.org/foro/entrar?user='.($userId ?? 'guest'),
                    commentCount: 2,
                ));
            }
        });

        $this->actingAs($user)->get('/eventos/'.$event->slug)->assertInertia(
            fn (Assert $page) => $page->component('Events/Show')
                ->where('discussion.discussion.url', "https://forum.example.org/d/event/{$event->id}")
                ->where('discussion.discussion.actionUrl', 'https://web.example.org/foro/entrar?user='.$user->id)
                ->where('discussion.discussion.commentCount', 2)
                ->where('discussion.topics', [])
        );
    }

    public function test_a_failing_forum_module_leaves_the_page_without_the_block(): void
    {
        $event = EventModel::factory()->published()->upcoming()->create();
        $this->app->instance(SubjectDiscussionInterface::class, new class implements SubjectDiscussionInterface
        {
            public function forSubject(string $type, string $id, ?string $userId): ?SubjectDiscussionDTO
            {
                throw new RuntimeException('The forum module failed');
            }
        });

        $this->get('/eventos/'.$event->slug)->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Events/Show')->where('discussion', null)
        );
    }
}
