<?php

declare(strict_types=1);

namespace Tests\Feature\SiteNotifications;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailPreferenceDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The site switch of each category, next to the mail one in the profile.
 */
final class SiteNotificationPreferencesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MailCategoryRegistryInterface::class)->registerMany([
            MailCategoryDTO::optional(key: 'example.replies', section: 'Example', label: 'Replies', description: 'D', site: true),
            MailCategoryDTO::optional(key: 'example.likes', section: 'Example', label: 'Likes', description: 'D', site: true, mail: false),
            MailCategoryDTO::mandatory(key: 'example.moderation', section: 'Example', label: 'Moderation', description: 'D', reason: 'R', site: true),
            MailCategoryDTO::optional(key: 'example.reminders', section: 'Example', label: 'Reminders', description: 'D'),
            MailCategoryDTO::optional(
                key: 'example.hidden',
                section: 'Example',
                label: 'Hidden',
                description: 'D',
                audience: static fn (object $user): bool => false,
                site: true,
            ),
        ], 'example');
    }

    public function test_sections_say_which_channels_each_category_offers_and_their_state(): void
    {
        $user = UserModel::factory()->create();
        $this->site()->update($user, ['example.replies' => false]);

        $categories = $this->categoriesOf($user);

        $this->assertTrue($categories['example.replies']->mail);
        $this->assertTrue($categories['example.replies']->site);
        $this->assertFalse($categories['example.replies']->siteEnabled);
        // Turning the site off leaves the mail as it was
        $this->assertTrue($categories['example.replies']->enabled);

        $this->assertFalse($categories['example.likes']->mail);
        $this->assertTrue($categories['example.likes']->siteEnabled);

        $this->assertTrue($categories['example.moderation']->siteEnabled);

        $this->assertFalse($categories['example.reminders']->site);
        $this->assertFalse($categories['example.reminders']->siteEnabled);
    }

    public function test_turning_the_site_back_on_shows_the_notices_again(): void
    {
        $user = UserModel::factory()->create();
        $this->site()->update($user, ['example.replies' => false]);
        $this->site()->update($user, ['example.replies' => true]);

        $this->assertTrue($this->categoriesOf($user)['example.replies']->siteEnabled);
    }

    public function test_turning_the_mail_off_leaves_the_site_on(): void
    {
        $user = UserModel::factory()->create();
        $this->app->make(MailPreferenceServiceInterface::class)->update($user, ['example.replies' => false]);

        $categories = $this->categoriesOf($user);

        $this->assertFalse($categories['example.replies']->enabled);
        $this->assertTrue($categories['example.replies']->siteEnabled);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notEditableOnSite(): array
    {
        return [
            'unknown' => ['example.unknown'],
            'mail only' => ['example.reminders'],
            'mandatory' => ['example.moderation'],
            'not shown to the person' => ['example.hidden'],
        ];
    }

    #[DataProvider('notEditableOnSite')]
    public function test_some_site_switches_cannot_be_changed_and_nothing_is_saved(string $key): void
    {
        $user = UserModel::factory()->create();

        try {
            $this->site()->update($user, ['example.replies' => false, $key => false]);
            $this->fail('The change should have been refused.');
        } catch (MailPreferenceNotEditableException $exception) {
            $this->assertSame($key, $exception->categoryKey);
        }

        $this->assertDatabaseCount('user_site_notification_preferences', 0);
    }

    public function test_a_category_shown_only_on_the_site_has_no_mail_switch(): void
    {
        $user = UserModel::factory()->create();

        $this->expectException(MailPreferenceNotEditableException::class);

        $this->app->make(MailPreferenceServiceInterface::class)->update($user, ['example.likes' => false]);
    }

    /**
     * @return array<string, MailPreferenceDTO>
     */
    private function categoriesOf(UserModel $user): array
    {
        $categories = [];
        foreach ($this->app->make(MailPreferenceServiceInterface::class)->sectionsFor($user) as $section) {
            foreach ($section->categories as $category) {
                $categories[$category->key] = $category;
            }
        }

        return $categories;
    }

    private function site(): SiteNotificationPreferenceServiceInterface
    {
        return $this->app->make(SiteNotificationPreferenceServiceInterface::class);
    }
}
