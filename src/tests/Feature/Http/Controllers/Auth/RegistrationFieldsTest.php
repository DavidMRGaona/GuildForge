<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Registration\Services\RegistrationFieldRegistryInterface;
use App\Domain\Events\RegistrationFieldsSubmitted;
use App\Domain\Events\UserRegistered;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Registration\FixtureRegistrationField;
use Tests\TestCase;

/**
 * Optional fields modules add to the registration form (core#699): drawn from the props,
 * checked on the server, announced after the account exists. A module never makes the
 * registration fail.
 */
final class RegistrationFieldsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string KEY = FixtureRegistrationField::KEY;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        FixtureRegistrationField::reset();
        $this->registry()->clear();
        $this->registry()->register(FixtureRegistrationField::class, 'fixture');
    }

    protected function tearDown(): void
    {
        FixtureRegistrationField::reset();

        parent::tearDown();
    }

    public function test_the_page_gets_the_available_fields_without_their_rules(): void
    {
        FixtureRegistrationField::$rules = ['accepted'];

        $this->get('/registro')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Auth/Register')
            ->where('registrationFields', [[
                'key' => self::KEY,
                'label' => 'Quiero recibir el boletín',
                'description' => 'Puedes darte de baja cuando quieras.',
                'type' => 'checkbox',
            ]]));
    }

    public function test_the_page_leaves_out_unavailable_and_failing_fields(): void
    {
        FixtureRegistrationField::$availableFailsWith = new RuntimeException('Provider unreachable');

        $this->get('/registro')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('registrationFields', []));
    }

    public function test_a_ticked_field_is_announced_with_the_new_account(): void
    {
        Event::fake([RegistrationFieldsSubmitted::class]);

        $this->register([self::KEY => true])->assertRedirect(route('home'));

        $user = UserModel::query()->where('email', 'nuevo@example.com')->sole();
        Event::assertDispatched(
            RegistrationFieldsSubmitted::class,
            static fn (RegistrationFieldsSubmitted $event): bool => $event->userId === (string) $user->id
                && $event->values === [self::KEY => true]
                && $event->ticked(self::KEY),
        );
    }

    public function test_an_unticked_or_missing_field_is_announced_as_false(): void
    {
        Event::fake([RegistrationFieldsSubmitted::class]);

        $this->register()->assertRedirect(route('home'));

        Event::assertDispatched(
            RegistrationFieldsSubmitted::class,
            static fn (RegistrationFieldsSubmitted $event): bool => $event->values === [self::KEY => false]
                && ! $event->ticked(self::KEY)
                && ! $event->ticked('other.field'),
        );
    }

    public function test_string_values_from_a_form_post_are_read_as_booleans(): void
    {
        Event::fake([RegistrationFieldsSubmitted::class]);

        $this->register([self::KEY => '1'])->assertRedirect(route('home'));

        Event::assertDispatched(
            RegistrationFieldsSubmitted::class,
            static fn (RegistrationFieldsSubmitted $event): bool => $event->values === [self::KEY => true],
        );
    }

    public function test_a_forged_field_is_dropped(): void
    {
        Event::fake([RegistrationFieldsSubmitted::class]);

        $this->register([self::KEY => true, 'other-module.admin' => true, 'role' => 'admin'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        Event::assertDispatched(
            RegistrationFieldsSubmitted::class,
            static fn (RegistrationFieldsSubmitted $event): bool => $event->values === [self::KEY => true],
        );
    }

    public function test_the_value_of_an_unavailable_field_is_dropped_and_nothing_is_announced(): void
    {
        Event::fake([RegistrationFieldsSubmitted::class]);
        FixtureRegistrationField::$available = false;

        $this->register([self::KEY => true])->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com']);
        Event::assertNotDispatched(RegistrationFieldsSubmitted::class);
    }

    public function test_a_field_whose_availability_throws_never_stops_the_registration(): void
    {
        Event::fake([RegistrationFieldsSubmitted::class]);
        FixtureRegistrationField::$availableFailsWith = new RuntimeException('Provider unreachable');

        $this->register([self::KEY => 'not a boolean'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com']);
        Event::assertNotDispatched(RegistrationFieldsSubmitted::class);
    }

    public function test_a_value_that_is_not_a_boolean_is_refused(): void
    {
        $this->register([self::KEY => 'yes'])->assertSessionHasErrors('registration_fields.'.self::KEY);

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_the_rules_of_the_module_apply_to_its_field(): void
    {
        FixtureRegistrationField::$rules = ['accepted'];

        $this->register([self::KEY => false])->assertSessionHasErrors('registration_fields.'.self::KEY);

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_a_rule_that_throws_drops_the_field_and_the_registration_goes_on(): void
    {
        Event::fake([RegistrationFieldsSubmitted::class]);
        $log = Log::spy();
        FixtureRegistrationField::$rules = [static function (): never {
            throw new RuntimeException('Rule failed');
        }];

        $this->register([self::KEY => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        Event::assertNotDispatched(RegistrationFieldsSubmitted::class);
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'Registration field could not be checked')
                && $context['key'] === self::KEY,
        )->once();
    }

    public function test_fields_that_are_not_a_list_of_values_are_refused(): void
    {
        $this->post('/registro', [...$this->account(), 'registration_fields' => 'newsletter'])
            ->assertSessionHasErrors('registration_fields');

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_a_listener_that_fails_never_stops_the_registration(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $log = Log::spy();
        Event::listen(RegistrationFieldsSubmitted::class, static function (): never {
            throw new RuntimeException('Mailing list down');
        });

        $this->register([self::KEY => true])->assertRedirect(route('home'));

        $user = UserModel::query()->where('email', 'nuevo@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(app(AuthorizationServiceInterface::class)->hasRole($user, 'member'));
        Notification::assertSentTo($user, WelcomeNotification::class);
        $log->shouldHaveReceived('error')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'RegistrationFieldsSubmitted')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'nuevo@example.com'),
        )->once();
    }

    public function test_nothing_is_announced_when_no_module_adds_fields(): void
    {
        Event::fake([RegistrationFieldsSubmitted::class, UserRegistered::class]);
        $this->registry()->clear();

        $this->register([self::KEY => true])->assertRedirect(route('home'));

        Event::assertNotDispatched(RegistrationFieldsSubmitted::class);
        Event::assertDispatched(
            UserRegistered::class,
            static fn (UserRegistered $event): bool => $event->email === 'nuevo@example.com',
        );
    }

    /**
     * @param  ?array<string, mixed>  $fields
     * @return TestResponse<Response>
     */
    private function register(?array $fields = null): TestResponse
    {
        return $this->post('/registro', [
            ...$this->account(),
            ...($fields === null ? [] : ['registration_fields' => $fields]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function account(): array
    {
        return [
            'name' => 'Nuevo usuario',
            'email' => 'nuevo@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];
    }

    private function registry(): RegistrationFieldRegistryInterface
    {
        return app(RegistrationFieldRegistryInterface::class);
    }
}
