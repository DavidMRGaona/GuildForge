<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Preferences;

use App\Application\Mail\Contracts\MailDeliveryRuleInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailPreferenceDTO;
use App\Application\Mail\DTOs\MailPreferenceSectionDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Domain\Mail\Enums\MailDelivery;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Mail\Preferences\MailCategoryRegistry;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class MailPreferenceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MailCategoryRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new MailCategoryRegistry(rejectsInvalidCategories: true);
        $this->app->instance(MailCategoryRegistryInterface::class, $this->registry);
        $this->registry->registerMany([
            MailCategoryDTO::mandatory(
                key: 'example.confirmations',
                section: 'Example section',
                label: 'Confirmations',
                description: 'What you did',
                reason: 'It confirms something you did',
            ),
            MailCategoryDTO::optional(
                key: 'example.reminders',
                section: 'Example section',
                label: 'Reminders',
                description: 'Before it starts',
            ),
            MailCategoryDTO::optional(
                key: 'example.moderation',
                section: 'Other section',
                label: 'Moderation',
                description: 'Pending review',
                audience: static fn (object $user): bool => $user instanceof UserModel && $user->name === 'Moderator',
            ),
        ], 'example');
    }

    public function test_it_lists_the_visible_categories_by_section_with_their_state(): void
    {
        $user = UserModel::factory()->create();
        $this->disable($user, 'example.reminders');

        $sections = $this->service()->sectionsFor($user);

        $this->assertEquals([
            new MailPreferenceSectionDTO(key: 'Example section', label: 'Example section', categories: [
                new MailPreferenceDTO(
                    key: 'example.confirmations',
                    label: 'Confirmations',
                    description: 'What you did',
                    mandatory: true,
                    mandatoryReason: 'It confirms something you did',
                    enabled: true,
                    digestible: false,
                    delivery: 'immediate',
                ),
                new MailPreferenceDTO(
                    key: 'example.reminders',
                    label: 'Reminders',
                    description: 'Before it starts',
                    mandatory: false,
                    mandatoryReason: null,
                    enabled: false,
                    digestible: false,
                    delivery: 'none',
                ),
            ]),
        ], $sections);
    }

    public function test_a_section_takes_the_icon_of_the_first_category_that_declares_one(): void
    {
        $this->registry->registerMany([
            MailCategoryDTO::optional(
                key: 'iconic.first',
                section: 'Iconic section',
                label: 'First',
                description: 'No icon here',
            ),
            MailCategoryDTO::optional(
                key: 'iconic.second',
                section: 'Iconic section',
                label: 'Second',
                description: 'Icon here',
                icon: 'dices',
            ),
            MailCategoryDTO::optional(
                key: 'iconic.third',
                section: 'Iconic section',
                label: 'Third',
                description: 'Another icon',
                icon: 'trophy',
            ),
        ], 'iconic');

        $sections = $this->service()->sectionsFor(UserModel::factory()->create());

        $this->assertSame([null, 'dices'], [$sections[0]->icon, $sections[1]->icon]);
    }

    public function test_a_category_shows_only_to_its_audience(): void
    {
        $moderator = UserModel::factory()->create(['name' => 'Moderator']);

        $sections = $this->service()->sectionsFor($moderator);

        $this->assertSame(['Example section', 'Other section'], array_map(
            static fn (MailPreferenceSectionDTO $section): string => $section->label,
            $sections,
        ));
        $this->assertSame('example.moderation', $sections[1]->categories[0]->key);
    }

    public function test_a_failing_audience_check_hides_the_category(): void
    {
        $log = Log::spy();
        $this->registry->register(MailCategoryDTO::optional(
            key: 'example.broken',
            section: 'Other section',
            label: 'Broken',
            description: 'Broken',
            audience: static fn (object $user): bool => throw new RuntimeException('Unavailable'),
        ), 'example');

        $sections = $this->service()->sectionsFor(UserModel::factory()->create());

        $this->assertCount(1, $sections);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_it_saves_the_optional_categories_a_person_sees(): void
    {
        $moderator = UserModel::factory()->create(['name' => 'Moderator']);

        $this->service()->update($moderator, ['example.reminders' => false, 'example.moderation' => false]);
        $this->service()->update($moderator, ['example.reminders' => true]);

        $this->assertSame(['example.moderation'], $this->disabled($moderator));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notEditable(): iterable
    {
        yield 'mandatory' => ['example.confirmations'];
        yield 'unknown' => ['example.unknown'];
        yield 'not visible' => ['example.moderation'];
    }

    #[DataProvider('notEditable')]
    public function test_it_refuses_a_category_the_person_cannot_change_and_saves_nothing(string $key): void
    {
        $user = UserModel::factory()->create();

        try {
            $this->service()->update($user, ['example.reminders' => false, $key => false]);
            $this->fail('The change should have been refused.');
        } catch (MailPreferenceNotEditableException $exception) {
            $this->assertSame($key, $exception->categoryKey);
        }

        $this->assertSame([], $this->disabled($user));
    }

    public function test_a_mandatory_category_is_always_received(): void
    {
        $user = UserModel::factory()->create();
        $this->disable($user, 'example.confirmations');

        $this->assertTrue($this->service()->receives($user, $this->category('example.confirmations')));
    }

    public function test_an_optional_category_is_received_unless_it_is_disabled(): void
    {
        $user = UserModel::factory()->create();

        $this->assertTrue($this->service()->receives($user, $this->category('example.reminders')));

        $this->disable($user, 'example.reminders');

        $this->assertFalse($this->service()->receives($user, $this->category('example.reminders')));
    }

    public function test_preferences_that_cannot_be_read_let_the_mail_through(): void
    {
        $log = Log::spy();
        $repository = Mockery::mock(MailPreferenceRepositoryInterface::class);
        $repository->shouldReceive('disabledFor')->andThrow(new RuntimeException('Database down'));
        $this->app->instance(MailPreferenceRepositoryInterface::class, $repository);

        $received = $this->service()->receives(UserModel::factory()->create(), $this->category('example.reminders'));

        $this->assertTrue($received);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_another_delivery_rule_can_hold_optional_mail_back(): void
    {
        $this->addRule(static fn (): bool => false);
        $user = UserModel::factory()->create();

        $this->assertFalse($this->service()->receives($user, $this->category('example.reminders')));
        $this->assertTrue($this->service()->receives($user, $this->category('example.confirmations')));
    }

    public function test_a_failing_delivery_rule_lets_the_mail_through(): void
    {
        $log = Log::spy();
        $this->addRule(static fn (): bool => throw new RuntimeException('Unavailable'));

        $this->assertTrue($this->service()->receives(UserModel::factory()->create(), $this->category('example.reminders')));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_the_rules_are_told_what_the_mail_is_about(): void
    {
        $seen = [];
        $this->addRule(static function (?MailSubjectDTO $subject) use (&$seen): bool {
            $seen[] = $subject?->id;

            return true;
        });
        $user = UserModel::factory()->create();

        $this->service()->receives($user, $this->category('example.reminders'), new MailSubjectDTO('example.campaign', 'c-1', 'La tumba'));
        $this->service()->receives($user, $this->category('example.reminders'));

        $this->assertSame(['c-1', null], $seen);
    }

    public function test_something_that_is_not_an_account_has_no_preferences(): void
    {
        $guest = new stdClass;

        $this->assertSame([], $this->service()->sectionsFor($guest));
        $this->assertTrue($this->service()->receives($guest, $this->category('example.reminders')));
    }

    public function test_a_digestible_category_offers_three_choices_and_saves_them(): void
    {
        $this->registerDigestible();
        $user = UserModel::factory()->create();

        $this->assertSame([true, 'immediate'], $this->stateOf($user, 'example.sign-ups'));

        $this->service()->update($user, ['example.sign-ups' => MailDelivery::Digest]);
        $this->assertSame([true, 'digest'], $this->stateOf($user, 'example.sign-ups'));

        $this->service()->update($user, ['example.sign-ups' => MailDelivery::None]);
        $this->assertSame([false, 'none'], $this->stateOf($user, 'example.sign-ups'));
        $this->assertSame(['example.sign-ups'], $this->disabled($user));

        // An unsubscribe link sends false: it turns the digest off too
        $this->service()->update($user, ['example.sign-ups' => MailDelivery::Digest]);
        $this->service()->update($user, ['example.sign-ups' => false]);
        $this->assertSame([false, 'none'], $this->stateOf($user, 'example.sign-ups'));

        $this->service()->update($user, ['example.sign-ups' => true]);
        $this->assertSame([true, 'immediate'], $this->stateOf($user, 'example.sign-ups'));
        $this->assertSame([], $this->disabled($user));
    }

    public function test_only_a_digestible_category_can_be_received_as_a_digest(): void
    {
        $this->registerDigestible();
        $user = UserModel::factory()->create();

        try {
            $this->service()->update($user, ['example.sign-ups' => MailDelivery::Digest, 'example.reminders' => MailDelivery::Digest]);
            $this->fail('The change should have been refused.');
        } catch (MailPreferenceNotEditableException $exception) {
            $this->assertSame('example.reminders', $exception->categoryKey);
        }

        $this->assertSame(0, DB::table('user_mail_preferences')->count());
        $this->service()->update($user, ['example.reminders' => MailDelivery::None]);
        $this->assertSame(['example.reminders'], $this->disabled($user));
    }

    public function test_the_digest_is_offered_only_where_the_module_allows_it(): void
    {
        $this->registerDigestible();
        $user = UserModel::factory()->create();

        $categories = $this->service()->sectionsFor($user)[0]->categories;

        $this->assertSame(
            ['example.confirmations' => false, 'example.reminders' => false, 'example.sign-ups' => true],
            array_combine(
                array_map(static fn (MailPreferenceDTO $category): string => $category->key, $categories),
                array_map(static fn (MailPreferenceDTO $category): bool => $category->digestible, $categories),
            ),
        );
    }

    public function test_a_mandatory_category_cannot_be_declared_digestible(): void
    {
        $this->assertFalse(MailCategoryDTO::mandatory('example.x', 'S', 'L', 'D', 'R')->digestible);
    }

    private function registerDigestible(): void
    {
        $this->registry->register(MailCategoryDTO::optional(
            key: 'example.sign-ups',
            section: 'Example section',
            label: 'Sign-ups',
            description: 'When someone joins',
            digestible: true,
        ), 'example');
    }

    /**
     * @return array{bool, string} [enabled, delivery] as the profile receives them
     */
    private function stateOf(UserModel $user, string $key): array
    {
        foreach ($this->service()->sectionsFor($user) as $section) {
            foreach ($section->categories as $category) {
                if ($category->key === $key) {
                    return [$category->enabled, $category->delivery];
                }
            }
        }

        $this->fail("No category {$key}");
    }

    /**
     * @param  Closure(?MailSubjectDTO): bool  $allows
     */
    private function addRule(Closure $allows): void
    {
        $rule = new readonly class($allows) implements MailDeliveryRuleInterface
        {
            /**
             * @param  Closure(?MailSubjectDTO): bool  $allows
             */
            public function __construct(private Closure $allows) {}

            public function allows(object $user, MailCategoryDTO $category, ?MailSubjectDTO $subject = null): bool
            {
                return ($this->allows)($subject);
            }
        };
        $this->app->instance('test.mail-rule', $rule);
        $this->app->tag(['test.mail-rule'], MailDeliveryRuleInterface::TAG);
    }

    private function category(string $key): MailCategoryDTO
    {
        $category = $this->registry->find($key);
        $this->assertNotNull($category);

        return $category;
    }

    private function disable(UserModel $user, string $key): void
    {
        $this->app->make(MailPreferenceRepositoryInterface::class)->save(UserId::fromString($user->id), [$key => false]);
    }

    /**
     * @return list<string>
     */
    private function disabled(UserModel $user): array
    {
        /** @var list<string> */
        return DB::table('user_mail_preferences')
            ->where('user_id', $user->id)
            ->where('enabled', false)
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    private function service(): MailPreferenceServiceInterface
    {
        return $this->app->make(MailPreferenceServiceInterface::class);
    }
}
