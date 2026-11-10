<?php

declare(strict_types=1);

namespace Tests\Integration\Mail;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Domain\Mail\Exceptions\InvalidMailCategoryException;
use App\Infrastructure\Mail\Preferences\MailCategoryRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Log;
use Tests\Support\Mail\CategorizedTestNotification;
use Tests\Support\Mail\UncategorizedTestNotification;
use Tests\TestCase;

final class MailCategoryRegistryTest extends TestCase
{
    public function test_register_and_find_keep_the_registration_order(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);
        $first = $this->optional('example.first');
        $second = $this->mandatory('example.second');

        $registry->registerMany([$first, $second], 'example');

        $this->assertSame($first, $registry->find('example.first'));
        $this->assertNull($registry->find('example.other'));
        $this->assertSame([$first, $second], $registry->all());
    }

    public function test_a_repeated_key_replaces_the_category(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);
        $registry->register($this->optional('example.first', 'Old'), 'example');
        $registry->register($this->optional('example.first', 'New'), 'example');

        $this->assertCount(1, $registry->all());
        $this->assertSame('New', $registry->find('example.first')?->label);
    }

    public function test_core_categories_use_the_core_prefix(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);
        $registry->register($this->optional('core.account'));

        $this->assertNotNull($registry->find('core.account'));

        $this->expectException(InvalidMailCategoryException::class);
        $registry->register($this->optional('example.first'));
    }

    public function test_a_module_cannot_declare_a_key_of_another_prefix(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);

        $this->expectException(InvalidMailCategoryException::class);
        $registry->register($this->optional('core.account'), 'example');
    }

    public function test_a_malformed_key_is_rejected(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);

        $this->expectException(InvalidMailCategoryException::class);
        $registry->register($this->optional('example'), 'example');
    }

    public function test_a_mandatory_category_needs_a_reason(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);

        $this->expectException(InvalidMailCategoryException::class);
        $registry->register(MailCategoryDTO::mandatory(
            key: 'example.first',
            section: 'example::mail.section',
            label: 'Label',
            description: 'Description',
            reason: ' ',
        ), 'example');
    }

    public function test_a_malformed_icon_name_is_rejected(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);

        $this->expectException(InvalidMailCategoryException::class);
        $registry->register(MailCategoryDTO::optional(
            key: 'example.first',
            section: 'example::mail.section',
            label: 'Label',
            description: 'Description',
            icon: 'Calendar Check',
        ), 'example');
    }

    public function test_a_kebab_case_icon_name_is_accepted(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);

        $registry->register(MailCategoryDTO::optional(
            key: 'example.first',
            section: 'example::mail.section',
            label: 'Label',
            description: 'Description',
            icon: 'calendar-check',
        ), 'example');

        $this->assertSame('calendar-check', $registry->find('example.first')?->icon);
    }

    public function test_a_category_is_mail_only_unless_it_says_otherwise(): void
    {
        $category = $this->optional('example.first');

        $this->assertTrue($category->mail);
        $this->assertFalse($category->site);
    }

    public function test_a_category_may_offer_site_notices_with_mail_or_without_it(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);

        $registry->registerMany([
            MailCategoryDTO::optional(
                key: 'example.replies',
                section: 'example::mail.section',
                label: 'Label',
                description: 'Description',
                site: true,
            ),
            MailCategoryDTO::optional(
                key: 'example.likes',
                section: 'example::mail.section',
                label: 'Label',
                description: 'Description',
                site: true,
                mail: false,
            ),
            MailCategoryDTO::mandatory(
                key: 'example.mentions',
                section: 'example::mail.section',
                label: 'Label',
                description: 'Description',
                reason: 'Reason',
                site: true,
            ),
        ], 'example');

        $replies = $registry->find('example.replies');
        $likes = $registry->find('example.likes');
        $mentions = $registry->find('example.mentions');
        $this->assertNotNull($replies);
        $this->assertNotNull($likes);
        $this->assertNotNull($mentions);

        $this->assertTrue($replies->site);
        $this->assertTrue($replies->mail);
        $this->assertFalse($likes->mail);
        $this->assertTrue($mentions->site);
    }

    public function test_a_category_without_any_channel_is_rejected(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);

        $this->expectException(InvalidMailCategoryException::class);
        $registry->register(MailCategoryDTO::optional(
            key: 'example.first',
            section: 'example::mail.section',
            label: 'Label',
            description: 'Description',
            mail: false,
        ), 'example');
    }

    public function test_a_digest_needs_mail(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);

        $this->expectException(InvalidMailCategoryException::class);
        $registry->register(MailCategoryDTO::optional(
            key: 'example.first',
            section: 'example::mail.section',
            label: 'Label',
            description: 'Description',
            digestible: true,
            site: true,
            mail: false,
        ), 'example');
    }

    public function test_outside_local_and_testing_an_invalid_category_is_skipped_with_a_warning(): void
    {
        $log = Log::spy();
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: false);

        $registry->register($this->optional('other.first'), 'example');
        $registry->register($this->optional('example.second'), 'example');

        $this->assertNull($registry->find('other.first'));
        $this->assertNotNull($registry->find('example.second'));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_it_finds_the_category_a_notification_declares(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);
        $category = $this->optional('example.first');
        $registry->register($category, 'example');

        $this->assertSame($category, $registry->forNotification(new CategorizedTestNotification('example.first')));
        $this->assertNull($registry->forNotification(new CategorizedTestNotification('example.unknown')));
        $this->assertNull($registry->forNotification(new UncategorizedTestNotification));
    }

    public function test_a_notification_class_it_cannot_change_is_assigned_to_a_category(): void
    {
        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);
        $category = $this->mandatory('core.account');
        $registry->register($category);

        $registry->assignNotification(ResetPassword::class, 'core.account');

        $this->assertSame($category, $registry->forNotification(new ResetPassword('token')));
        // A subclass keeps the category of the class it extends
        $this->assertSame($category, $registry->forNotification(new class('token') extends ResetPassword {}));
    }

    private function optional(string $key, string $label = 'Label'): MailCategoryDTO
    {
        return MailCategoryDTO::optional(
            key: $key,
            section: 'example::mail.section',
            label: $label,
            description: 'Description',
        );
    }

    private function mandatory(string $key): MailCategoryDTO
    {
        return MailCategoryDTO::mandatory(
            key: $key,
            section: 'example::mail.section',
            label: 'Label',
            description: 'Description',
            reason: 'Reason',
        );
    }
}
