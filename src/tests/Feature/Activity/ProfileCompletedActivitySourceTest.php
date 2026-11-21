<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Activity\Sources\ProfileCompletedActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Activity\ActivitySourceContractTests;
use Tests\Support\Activity\FakesActivityEvents;
use Tests\TestCase;

final class ProfileCompletedActivitySourceTest extends TestCase
{
    use ActivitySourceContractTests;
    use FakesActivityEvents;
    use LazilyRefreshDatabase;

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(ProfileCompletedActivitySource::class);
    }

    protected function activitySourceOwner(): string
    {
        return 'core';
    }

    protected function createCountedActivitySubject(): string
    {
        return (string) $this->completeAccount()->id;
    }

    private function completeAccount(): UserModel
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana', 'avatar_public_id' => 'avatars/2026/11/ana.png']);
        $this->writeBio($user, 'Juego de rol desde 1999.');

        return $user;
    }

    private function writeBio(UserModel $user, ?string $bio): void
    {
        // As the profile service does: through the model, so its observers run
        $profile = UserProfileModel::query()->findOrFail($user->id);
        $profile->bio = $bio;
        $profile->save();
    }

    public function test_it_is_the_core_profile_completed_source(): void
    {
        $source = $this->activitySource();

        $this->assertSame('core.profile-completed', $source->type());
        $this->assertSame(ActivityKind::ProfileCompleted, $source->kind());
    }

    public function test_a_complete_profile_is_an_owner_fact(): void
    {
        $user = $this->completeAccount();

        $facts = $this->activitySource()->facts((string) $user->id);

        $this->assertCount(1, $facts);
        $this->assertSame((string) $user->id, $facts[0]->userId);
        $this->assertSame(ActivityRole::Owner, $facts[0]->role);
    }

    public function test_it_dates_the_moment_the_profile_became_complete_and_keeps_it_while_it_stays_complete(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana', 'avatar_public_id' => 'avatars/ana.png']);
        $this->travelTo(now()->addDay()->startOfSecond());
        $completedAt = now();
        $this->writeBio($user, 'Hola.');
        $this->travelTo(now()->addDays(3));

        // Unrelated changes do not move the date
        UserProfileModel::query()->findOrFail($user->id)->update(['visibility' => ProfileVisibility::Public]);
        $this->writeBio($user, 'Hola de nuevo.');

        $facts = $this->activitySource()->facts((string) $user->id);

        $this->assertSame($completedAt->getTimestamp(), $facts[0]->occurredAt->getTimestamp());
    }

    /**
     * @return array<string, array{array<string, mixed>, ?string}>
     */
    public static function incompleteAccounts(): array
    {
        return [
            'no public name' => [['display_name' => null, 'avatar_public_id' => 'a.png'], 'Hola'],
            'no avatar' => [['display_name' => 'Ana', 'avatar_public_id' => null], 'Hola'],
            'no about me' => [['display_name' => 'Ana', 'avatar_public_id' => 'a.png'], null],
            'blank about me' => [['display_name' => 'Ana', 'avatar_public_id' => 'a.png'], '   '],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('incompleteAccounts')]
    public function test_a_profile_missing_a_part_does_not_count(array $attributes, ?string $bio): void
    {
        $user = UserModel::factory()->create($attributes);
        $this->writeBio($user, $bio);
        $counted = $this->createCountedActivitySubject();

        $this->assertSame([], $this->activitySource()->facts((string) $user->id));
        $this->assertNotContains((string) $user->id, $this->listedSubjects());
        $this->assertContains($counted, $this->listedSubjects());
    }

    public function test_a_profile_that_stops_being_complete_has_no_facts_and_becomes_complete_again_later(): void
    {
        $user = $this->completeAccount();
        $user->update(['avatar_public_id' => null]);

        $whileIncomplete = $this->activitySource()->facts((string) $user->id);

        $user->update(['avatar_public_id' => 'avatars/2026/11/new.png']);
        $afterCompleting = $this->activitySource()->facts((string) $user->id);

        $this->assertSame([], $whileIncomplete);
        $this->assertCount(1, $afterCompleting);
    }

    public function test_an_anonymized_or_deactivated_account_does_not_count(): void
    {
        $anonymized = $this->completeAccount();
        $deactivated = $this->completeAccount();
        $this->app->make(UserServiceInterface::class)->anonymize((string) $anonymized->id);
        $deactivated->delete();

        $this->assertSame([], $this->activitySource()->facts((string) $anonymized->id));
        $this->assertSame([], $this->activitySource()->facts((string) $deactivated->id));
        $this->assertNotContains((string) $deactivated->id, $this->listedSubjects());

        $deactivated->restore();
        $this->assertCount(1, $this->activitySource()->facts((string) $deactivated->id));
    }

    public function test_the_answer_does_not_depend_on_who_is_signed_in(): void
    {
        $user = $this->completeAccount();
        $other = UserModel::factory()->create();

        $guest = $this->activitySource()->facts((string) $user->id);
        $this->actingAs($other);

        $this->assertEquals($guest, $this->activitySource()->facts((string) $user->id));
    }

    public function test_a_hidden_profile_still_counts_but_is_described_without_a_link_or_name(): void
    {
        $user = $this->completeAccount();
        UserProfileModel::query()->whereKey($user->id)->update(['visibility' => ProfileVisibility::Hidden]);
        $viewer = UserModel::factory()->create();

        $this->assertCount(1, $this->activitySource()->facts((string) $user->id));

        $described = $this->activitySource()->describe([(string) $user->id], $viewer)[(string) $user->id];
        $this->assertNull($described->url);
        $this->assertStringNotContainsString('Ana', $described->title);

        $own = $this->activitySource()->describe([(string) $user->id], $user)[(string) $user->id];
        $this->assertNotNull($own->url);
    }

    public function test_history_pages_have_a_stable_order_and_an_opaque_cursor(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->completeAccount();
        }
        $source = $this->activitySource();

        $first = $source->history(null, 2);
        $second = $source->history($first->nextCursor, 2);

        $this->assertNotNull($first->nextCursor);
        $this->assertNotSame($first->subjects[0]->subjectId, $second->subjects[0]->subjectId);
        $this->assertEquals($first, $source->history(null, 2));
    }

    public function test_an_unreadable_cursor_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->activitySource()->history('not a cursor', 10);
    }

    public function test_saving_the_about_me_publishes_the_profile(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->fakeActivityEvents();

        $this->writeBio($user, 'Hola.');

        $this->assertProfilePublished($user);
    }

    public function test_changing_the_avatar_publishes_the_profile(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->fakeActivityEvents();

        $user->update(['avatar_public_id' => 'avatars/ana.png']);

        $this->assertProfilePublished($user);
    }

    public function test_changing_the_public_name_publishes_the_profile(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->fakeActivityEvents();

        $user->update(['display_name' => 'Ana María']);

        $this->assertProfilePublished($user);
    }

    private function assertProfilePublished(UserModel $user): void
    {
        $this->assertActivityPublished('core.profile-completed', (string) $user->id);
    }

    /**
     * @return list<string>
     */
    private function listedSubjects(): array
    {
        $listed = [];
        $cursor = null;

        do {
            $page = $this->activitySource()->history($cursor, 50);
            foreach ($page->subjects as $subject) {
                $listed[] = $subject->subjectId;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return $listed;
    }

    public function test_changes_that_do_not_touch_the_profile_publish_nothing(): void
    {
        $user = $this->completeAccount();
        $this->fakeActivityEvents();

        $user->update(['email' => 'other@example.com']);

        $this->assertNoActivityPublished();
    }

    public function test_anonymizing_deactivating_and_restoring_publish_the_profile_again(): void
    {
        $anonymized = $this->completeAccount();
        $deactivated = $this->completeAccount();
        $this->fakeActivityEvents();

        $this->app->make(UserServiceInterface::class)->anonymize((string) $anonymized->id);
        $deactivated->delete();
        $deactivated->restore();

        foreach ([$anonymized, $deactivated] as $user) {
            $this->assertActivityPublished('core.profile-completed', (string) $user->id);
        }
    }
}
