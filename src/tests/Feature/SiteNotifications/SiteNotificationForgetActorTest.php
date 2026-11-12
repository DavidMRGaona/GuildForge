<?php

declare(strict_types=1);

namespace Tests\Feature\SiteNotifications;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\Mail\Repositories\MailDigestItemRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\SiteNotificationModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\TestCase;

/**
 * An anonymized account must not be named by the notices other people got because of it (core#537).
 */
final class SiteNotificationForgetActorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MailCategoryRegistryInterface::class)->register(
            MailCategoryDTO::optional(key: 'example.replies', section: 'Example', label: 'Replies', description: 'D', site: true),
            'example',
        );
    }

    public function test_only_the_notices_with_that_account_as_actor_go(): void
    {
        $ana = UserModel::factory()->create();
        $luis = UserModel::factory()->create();
        $owner = UserModel::factory()->create();
        $this->notify($owner, '/de-ana', (string) $ana->id);
        $this->notify($owner, '/de-luis', (string) $luis->id);
        $this->notify($owner, '/sin-actor', null);

        $this->service()->forgetActor((string) $ana->id);

        $this->assertSame(['/de-luis', '/sin-actor'], $this->urlsOf($owner));
    }

    public function test_read_and_unread_notices_both_go_and_the_bell_count_follows(): void
    {
        $ana = UserModel::factory()->create();
        $owner = UserModel::factory()->create();
        $this->notify($owner, '/leido', (string) $ana->id);
        $this->notify($owner, '/sin-leer', (string) $ana->id);
        $this->service()->open($owner, (string) SiteNotificationModel::query()->where('url', '/leido')->value('id'));

        $this->service()->forgetActor((string) $ana->id);

        $this->assertSame(0, $this->service()->total($owner));
        $this->assertSame(0, $this->service()->unreadCount($owner));
    }

    public function test_a_group_with_other_actors_goes_too_because_its_title_may_name_the_account(): void
    {
        $ana = UserModel::factory()->create();
        $luis = UserModel::factory()->create();
        $marta = UserModel::factory()->create();
        $owner = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        foreach ([$luis, $marta, $ana] as $actor) {
            $this->notify($owner, '/tema', (string) $actor->id, 'topic:1', $actor->name);
        }
        $this->notify($other, '/otro-tema', (string) $luis->id, 'topic:2', $luis->name);
        $this->assertSame(3, SiteNotificationModel::query()->where('user_id', $owner->id)->value('count'));

        $this->service()->forgetActor((string) $ana->id);

        $this->assertSame([], $this->urlsOf($owner));
        $this->assertSame(['/otro-tema'], $this->urlsOf($other));
    }

    public function test_a_group_that_never_had_the_account_is_kept(): void
    {
        $ana = UserModel::factory()->create();
        $luis = UserModel::factory()->create();
        $marta = UserModel::factory()->create();
        $owner = UserModel::factory()->create();
        $this->notify($owner, '/tema', (string) $luis->id, 'topic:1', $luis->name);
        $this->notify($owner, '/tema', (string) $marta->id, 'topic:1', $marta->name);

        $this->service()->forgetActor((string) $ana->id);

        $this->assertSame(1, SiteNotificationModel::query()->where('user_id', $owner->id)->count());
        $this->assertSame(2, SiteNotificationModel::query()->where('user_id', $owner->id)->value('count'));
    }

    public function test_forgetting_twice_or_an_unknown_account_does_nothing(): void
    {
        $ana = UserModel::factory()->create();
        $owner = UserModel::factory()->create();
        $this->notify($owner, '/de-ana', (string) $ana->id);
        $this->notify($owner, '/sin-actor', null);

        $this->service()->forgetActor((string) $ana->id);
        $this->service()->forgetActor((string) $ana->id);
        $this->service()->forgetActor('00000000-0000-4000-8000-000000000000');

        $this->assertSame(['/sin-actor'], $this->urlsOf($owner));
    }

    public function test_the_pending_digest_lines_of_that_actor_go_and_the_rest_stay(): void
    {
        $ana = UserModel::factory()->create();
        $luis = UserModel::factory()->create();
        $owner = UserModel::factory()->create();
        $items = app(MailDigestItemRepositoryInterface::class);
        $ownerId = UserId::fromString($owner->id);
        $items->add($ownerId, 'example.sign-ups', 'Ana se ha apuntado', null, null, null, (string) $ana->id);
        $items->add($ownerId, 'example.sign-ups', 'Luis se ha apuntado', null, null, null, (string) $luis->id);
        $items->add($ownerId, 'example.sign-ups', 'Hay una mesa nueva', null, null, null);

        $items->deleteForActor((string) $ana->id);
        $items->deleteForActor((string) $ana->id);

        $this->assertEqualsCanonicalizing(
            ['Luis se ha apuntado', 'Hay una mesa nueva'],
            DB::table('user_mail_digest_items')->pluck('line')->all(),
        );
    }

    private function notify(UserModel $owner, string $url, ?string $actorId, ?string $groupKey = null, string $name = 'Alguien'): void
    {
        $owner->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(
            title: $name.' ha respondido',
            url: $url,
            groupKey: $groupKey,
            actorId: $actorId,
            groupedTitle: static fn (SiteNotificationGroupDTO $group): string => $name.' y '.$group->otherActors.' más',
        )));
    }

    /**
     * @return list<string>
     */
    private function urlsOf(UserModel $owner): array
    {
        /** @var list<string> */
        return SiteNotificationModel::query()->where('user_id', $owner->id)->orderBy('url')->pluck('url')->all();
    }

    private function service(): SiteNotificationServiceInterface
    {
        return $this->app->make(SiteNotificationServiceInterface::class);
    }
}
