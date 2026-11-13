<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Preferences;

use App\Application\Mail\DTOs\MailPreferenceDTO;
use App\Application\Mail\DTOs\MailPreferenceSectionDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Domain\SiteNews\SiteNewsKind;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\InactiveRolesManagerNotification;
use App\Notifications\InactiveRolesNotification;
use App\Notifications\SiteNewsNotification;
use App\Notifications\VerifyPendingEmailNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CoreMailCategoriesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_account_category_is_mandatory_and_role_management_optional(): void
    {
        $registry = $this->app->make(MailCategoryRegistryInterface::class);

        $this->assertTrue($registry->find(CoreMailCategories::ACCOUNT)?->isMandatory());
        $this->assertFalse($registry->find(CoreMailCategories::ROLE_MANAGEMENT)?->isMandatory());
    }

    public function test_inactive_role_notices_to_managers_can_wait_for_the_daily_digest(): void
    {
        app()->setLocale('es');
        $registry = $this->app->make(MailCategoryRegistryInterface::class);

        $this->assertTrue($registry->find(CoreMailCategories::ROLE_MANAGEMENT)?->digestible);
        $this->assertFalse($registry->find(CoreMailCategories::ACCOUNT)?->digestible);
        $this->assertSame(
            'Ana tiene roles en pausa: Tesorería',
            (new InactiveRolesManagerNotification('Ana', 'Tesorería', 'id', ['treasury']))->mailDigestLine(new \stdClass)->text,
        );
    }

    /**
     * @return iterable<string, array{Notification, string}>
     */
    public static function coreNotifications(): iterable
    {
        yield 'email verification' => [new VerifyEmail, CoreMailCategories::ACCOUNT];
        yield 'password reset' => [new ResetPassword('token'), CoreMailCategories::ACCOUNT];
        yield 'email change' => [new VerifyPendingEmailNotification('new@example.com'), CoreMailCategories::ACCOUNT];
        yield 'welcome' => [new WelcomeNotification('Example site'), CoreMailCategories::ACCOUNT];
        yield 'inactive roles' => [new InactiveRolesNotification('Example site', 'Role'), CoreMailCategories::ACCOUNT];
        yield 'inactive roles to managers' => [new InactiveRolesManagerNotification('Person', 'Role', 'id', ['role']), CoreMailCategories::ROLE_MANAGEMENT];
        yield 'site news' => [new SiteNewsNotification(SiteNewsKind::Event, '00000000-0000-4000-8000-000000000000'), CoreMailCategories::SITE_NEWS];
    }

    #[DataProvider('coreNotifications')]
    public function test_every_core_mail_has_its_category(Notification $notification, string $category): void
    {
        $this->assertSame(
            $category,
            $this->app->make(MailCategoryRegistryInterface::class)->forNotification($notification)?->key,
        );
    }

    public function test_a_person_sees_the_account_category_locked_with_its_reason(): void
    {
        $sections = $this->app->make(MailPreferenceServiceInterface::class)->sectionsFor(UserModel::factory()->create());

        $this->assertSame([__('mail.preferences.sections.account'), __('mail.preferences.sections.news')], $this->labels($sections));
        $account = $sections[0]->categories[0];
        $this->assertSame(CoreMailCategories::ACCOUNT, $account->key);
        $this->assertTrue($account->mandatory);
        $this->assertTrue($account->enabled);
        $this->assertSame(__('mail.preferences.categories.account.reason'), $account->mandatoryReason);
        $this->assertNotSame('mail.preferences.categories.account.label', $account->label);
    }

    public function test_role_management_shows_to_whoever_can_assign_roles(): void
    {
        $sections = $this->app->make(MailPreferenceServiceInterface::class)->sectionsFor(UserModel::factory()->admin()->create());

        $this->assertSame([
            __('mail.preferences.sections.account'),
            __('mail.preferences.sections.site_management'),
            __('mail.preferences.sections.proposals'),
            __('mail.preferences.sections.news'),
        ], $this->labels($sections));
        $this->assertSame(
            [CoreMailCategories::ROLE_MANAGEMENT],
            array_map(static fn (MailPreferenceDTO $category): string => $category->key, $sections[1]->categories),
        );
    }

    /**
     * @param  list<MailPreferenceSectionDTO>  $sections
     * @return list<string>
     */
    private function labels(array $sections): array
    {
        return array_map(static fn (MailPreferenceSectionDTO $section): string => $section->label, $sections);
    }
}
