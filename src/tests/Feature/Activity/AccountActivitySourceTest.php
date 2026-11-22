<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Profiles\DTOs\ProfileUpdateDTO;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Activity\Sources\AccountActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Activity\ActivitySourceContractTests;
use Tests\Support\Activity\FakesActivityEvents;
use Tests\TestCase;

final class AccountActivitySourceTest extends TestCase
{
    use ActivitySourceContractTests;
    use FakesActivityEvents;
    use LazilyRefreshDatabase;

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(AccountActivitySource::class);
    }

    protected function activitySourceOwner(): string
    {
        return 'core';
    }

    protected function createCountedActivitySubject(): string
    {
        return (string) UserModel::factory()->create()->id;
    }

    public function test_it_is_the_core_account_source(): void
    {
        $source = $this->activitySource();

        $this->assertSame('core.account', $source->type());
        $this->assertSame(ActivityKind::Account, $source->kind());
    }

    public function test_an_account_is_an_owner_fact_dated_when_it_was_created(): void
    {
        $user = UserModel::factory()->create(['created_at' => '2026-03-04 10:15:00']);

        $facts = $this->activitySource()->facts((string) $user->id);

        $this->assertCount(1, $facts);
        $this->assertSame((string) $user->id, $facts[0]->userId);
        $this->assertSame(ActivityRole::Owner, $facts[0]->role);
        $this->assertSame($user->created_at?->getTimestamp(), $facts[0]->occurredAt->getTimestamp());
        $this->assertSame([], $facts[0]->attributes);
    }

    public function test_an_anonymized_or_deactivated_account_does_not_count(): void
    {
        $anonymized = UserModel::factory()->create();
        $deactivated = UserModel::factory()->create();
        $this->app->make(UserServiceInterface::class)->anonymize((string) $anonymized->id);
        $deactivated->delete();
        $kept = $this->createCountedActivitySubject();

        $this->assertSame([], $this->activitySource()->facts((string) $anonymized->id));
        $this->assertSame([], $this->activitySource()->facts((string) $deactivated->id));

        $listed = $this->listedSubjects();
        $this->assertContains($kept, $listed);
        $this->assertNotContains((string) $anonymized->id, $listed);
        $this->assertNotContains((string) $deactivated->id, $listed);
    }

    public function test_the_answer_is_the_same_for_a_guest_and_a_signed_in_member(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        $guest = $this->activitySource()->facts((string) $user->id);
        $this->actingAs($other);
        $signedIn = $this->activitySource()->facts((string) $user->id);

        $this->assertEquals($guest, $signedIn);
    }

    public function test_the_core_reads_it_through_the_reader(): void
    {
        $user = UserModel::factory()->create();

        $result = $this->app->make(ActivityReaderInterface::class)->facts('core.account', (string) $user->id);

        $this->assertTrue($result->isRead());
        $this->assertCount(1, $result->facts);
    }

    public function test_history_pages_have_a_stable_order_and_an_opaque_cursor(): void
    {
        UserModel::factory()->count(5)->create();
        $source = $this->activitySource();

        $first = $source->history(null, 2);
        $second = $source->history($first->nextCursor, 2);

        $this->assertCount(2, $first->subjects);
        $this->assertNotNull($first->nextCursor);
        $this->assertNotSame($first->subjects[0]->subjectId, $second->subjects[0]->subjectId);
        $this->assertEquals($first, $source->history(null, 2), 'The same cursor gives the same page.');
    }

    public function test_an_unreadable_cursor_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->activitySource()->history('not a cursor', 10);
    }

    public function test_describe_links_only_a_profile_the_viewer_may_see(): void
    {
        $owner = UserModel::factory()->create(['display_name' => 'Ana']);
        $viewer = UserModel::factory()->create();
        $handle = UserProfileModel::query()->findOrFail($owner->id)->handle;
        $source = $this->activitySource();

        // Members is the default visibility: a signed-in member sees it, a guest does not
        $asMember = $source->describe([(string) $owner->id], $viewer)[(string) $owner->id];
        $this->assertStringContainsString('Ana', $asMember->title);
        $this->assertSame('/miembros/'.$handle, $asMember->url);

        $asGuest = $source->describe([(string) $owner->id], null)[(string) $owner->id];
        $this->assertNull($asGuest->url);
        $this->assertStringNotContainsString('Ana', $asGuest->title);
    }

    public function test_describe_leaves_out_an_account_that_does_not_count_even_for_its_owner(): void
    {
        $owner = UserModel::factory()->create(['display_name' => 'Ana']);
        UserProfileModel::query()->whereKey($owner->id)->update(['visibility' => ProfileVisibility::Hidden]);

        $this->assertSame([], $this->activitySource()->describe([(string) $owner->id], $owner));
        $this->assertSame([], $this->activitySource()->describe([(string) $owner->id], UserModel::factory()->create()));
    }

    /**
     * @return array<string, array{ProfileVisibility, bool}>
     */
    public static function visibilities(): array
    {
        return [
            'public' => [ProfileVisibility::Public, true],
            'members' => [ProfileVisibility::Members, true],
            'association' => [ProfileVisibility::Association, false],
            'hidden' => [ProfileVisibility::Hidden, false],
        ];
    }

    /**
     * Only what any signed-in member can know exists: an account whose profile only the association or its owner may see is left out
     */
    #[DataProvider('visibilities')]
    public function test_only_an_account_every_member_may_see_counts(ProfileVisibility $visibility, bool $counts): void
    {
        $user = UserModel::factory()->create();
        UserProfileModel::query()->whereKey($user->id)->update(['visibility' => $visibility]);

        $this->assertSame($counts, $this->activitySource()->facts((string) $user->id) !== []);
        $this->assertSame($counts, in_array((string) $user->id, $this->listedSubjects(), true));
    }

    public function test_an_account_without_a_public_name_does_not_count(): void
    {
        $user = UserModel::factory()->create(['display_name' => null]);

        $this->assertSame([], $this->activitySource()->facts((string) $user->id));
        $this->assertNotContains((string) $user->id, $this->listedSubjects());
    }

    public function test_changing_the_visibility_publishes_the_account(): void
    {
        $user = UserModel::factory()->create();
        $this->fakeActivityEvents();

        UserProfileModel::query()->findOrFail($user->id)->update(['visibility' => ProfileVisibility::Hidden]);

        $this->assertActivityPublished('core.account', (string) $user->id);
        $this->assertActivityPublished('core.profile-completed', (string) $user->id);
    }

    public function test_the_profile_service_changing_the_visibility_publishes_the_account(): void
    {
        $user = UserModel::factory()->create();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        $this->fakeActivityEvents();

        $this->app->make(UserProfileServiceInterface::class)->update((string) $user->id, new ProfileUpdateDTO(
            handle: $profile->handle,
            visibility: ProfileVisibility::Association,
            bio: null,
            showMembership: false,
            sections: [],
            stats: [],
            offeredSections: [],
            offeredStats: [],
        ));

        $this->assertActivityPublished('core.account', (string) $user->id);
        $this->assertSame([], $this->activitySource()->facts((string) $user->id));
    }

    public function test_setting_and_clearing_the_public_name_publish_the_account(): void
    {
        $user = UserModel::factory()->create(['display_name' => null]);
        $this->fakeActivityEvents();

        $user->update(['display_name' => 'Ana']);
        $this->assertActivityPublished('core.account', (string) $user->id);

        $this->fakeActivityEvents();
        $user->update(['display_name' => null]);
        $this->assertActivityPublished('core.account', (string) $user->id);
    }

    public function test_renaming_publishes_nothing_about_the_account(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->fakeActivityEvents();

        $user->update(['display_name' => 'Ana María']);

        $this->assertNoActivityPublished();
    }

    public function test_creating_an_account_publishes_it(): void
    {
        $this->fakeActivityEvents();

        $user = UserModel::factory()->create();

        $this->assertActivityPublished('core.account', (string) $user->id);
    }

    public function test_anonymizing_deactivating_and_restoring_an_account_publish_it_again(): void
    {
        $anonymized = UserModel::factory()->create();
        $deactivated = UserModel::factory()->create();
        $this->fakeActivityEvents();

        $this->app->make(UserServiceInterface::class)->anonymize((string) $anonymized->id);
        $deactivated->delete();
        $deactivated->restore();

        $this->assertActivityPublished('core.account', (string) $anonymized->id);
        $this->assertActivityPublished('core.account', (string) $deactivated->id);
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
}
