<?php

declare(strict_types=1);

namespace Tests\Feature\WebPush;

use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Services\UserServiceInterface;
use App\Application\WebPush\Services\WebPushPreferenceInterface;
use App\Application\WebPush\Services\WebPushPreferenceServiceInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Domain\ValueObjects\UserId;
use App\Domain\WebPush\Repositories\WebPushPreferenceRepositoryInterface;
use App\Infrastructure\Mail\Preferences\MailCategoryRegistry;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Each person picks, per category with site notices, what also reaches their browsers as a push
 * message (core#804). Off for every category until they choose it.
 */
final class WebPushPreferenceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MailCategoryRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new MailCategoryRegistry(rejectsInvalidCategories: true);
        $this->app->instance(MailCategoryRegistryInterface::class, $this->registry);
        $this->registry->registerMany([
            MailCategoryDTO::optional(
                key: 'example.replies',
                section: 'Example section',
                label: 'Replies',
                description: 'Replies in topics you follow',
                site: true,
                pushSuggested: true,
            ),
            MailCategoryDTO::mandatory(
                key: 'example.account',
                section: 'Example section',
                label: 'Account',
                description: 'Your account',
                reason: 'It is about your account',
                site: true,
            ),
            MailCategoryDTO::optional(
                key: 'example.mail-only',
                section: 'Example section',
                label: 'Mail only',
                description: 'Only by mail',
            ),
            MailCategoryDTO::optional(
                key: 'example.moderation',
                section: 'Example section',
                label: 'Moderation',
                description: 'Pending review',
                audience: static fn (object $user): bool => $user instanceof UserModel && $user->name === 'Moderator',
                site: true,
            ),
        ], 'example');
    }

    public function test_nothing_is_pushed_until_the_person_chooses_it_not_even_a_suggested_category(): void
    {
        $user = UserModel::factory()->create();

        $this->assertFalse($this->wantsPush($user, 'example.replies'));
        $this->assertFalse($this->wantsPush($user, 'example.account'));
    }

    public function test_a_category_turned_on_is_pushed_until_it_is_turned_off_again(): void
    {
        $user = UserModel::factory()->create();

        $this->service()->update($user, ['example.replies' => true]);
        $this->assertTrue($this->wantsPush($user, 'example.replies'));
        $this->assertSame(['example.replies'], $this->service()->enabledFor($user));

        $this->service()->update($user, ['example.replies' => false]);
        $this->assertFalse($this->wantsPush($user, 'example.replies'));
        // Only the categories turned on are kept
        $this->assertDatabaseCount('user_web_push_preferences', 0);
    }

    public function test_the_choice_of_one_account_never_reaches_another(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        $this->service()->update($user, ['example.replies' => true]);

        $this->assertFalse($this->wantsPush($other, 'example.replies'));
    }

    public function test_nothing_is_saved_when_one_category_cannot_be_chosen(): void
    {
        $user = UserModel::factory()->create();

        // A mandatory category is listed apart, without switches: it reaches the bell and the mail
        foreach (['example.unknown', 'example.mail-only', 'example.moderation', 'example.account'] as $refused) {
            try {
                $this->service()->update($user, ['example.replies' => true, $refused => true]);
                $this->fail("{$refused} was accepted");
            } catch (MailPreferenceNotEditableException $exception) {
                $this->assertSame($refused, $exception->categoryKey);
            }
        }

        $this->assertDatabaseCount('user_web_push_preferences', 0);
    }

    public function test_a_category_shown_only_to_some_is_chosen_by_them(): void
    {
        $moderator = UserModel::factory()->create(['name' => 'Moderator']);

        $this->service()->update($moderator, ['example.moderation' => true]);

        $this->assertTrue($this->wantsPush($moderator, 'example.moderation'));
    }

    public function test_choices_that_cannot_be_read_push_nothing(): void
    {
        $user = UserModel::factory()->create();
        $repository = Mockery::mock(WebPushPreferenceRepositoryInterface::class);
        $repository->shouldReceive('enabledFor')->andThrow(new RuntimeException('Database down'));
        $this->app->instance(WebPushPreferenceRepositoryInterface::class, $repository);
        $log = Log::spy();

        $this->assertFalse($this->wantsPush($user, 'example.replies'));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_an_anonymized_account_loses_its_choices(): void
    {
        $user = UserModel::factory()->create();
        $heir = UserModel::factory()->create();
        $this->service()->update($user, ['example.replies' => true]);
        $this->service()->update($heir, ['example.replies' => true]);

        app(UserServiceInterface::class)->anonymizeWithContentTransfer(new AnonymizeUserDTO(
            userId: $user->id,
            contentAction: 'transfer',
            transferToUserId: $heir->id,
        ));

        $this->assertSame([], app(WebPushPreferenceRepositoryInterface::class)->enabledFor(UserId::fromString($user->id)));
        $this->assertTrue($this->wantsPush($heir, 'example.replies'));
    }

    public function test_a_deactivated_account_keeps_its_choices_for_when_it_comes_back(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->update($user, ['example.replies' => true]);

        $user->delete();

        $this->assertDatabaseCount('user_web_push_preferences', 1);
    }

    private function wantsPush(UserModel $user, string $key): bool
    {
        $category = $this->registry->find($key);
        $this->assertNotNull($category);

        return app(WebPushPreferenceInterface::class)->wantsPush($user->id, $category, null);
    }

    private function service(): WebPushPreferenceServiceInterface
    {
        return app(WebPushPreferenceServiceInterface::class);
    }
}
