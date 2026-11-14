<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Registration;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Registration\Contracts\RegistrationFieldInterface;
use App\Application\Registration\DTOs\RegistrationFieldDTO;
use App\Application\Registration\Services\RegistrationFieldRegistryInterface;
use App\Domain\Registration\Enums\RegistrationFieldType;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Closure;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Tests\Support\Registration\FixtureRegistrationField;
use Tests\TestCase;

final class RegistrationFieldRegistryTest extends TestCase
{
    private int $fields = 0;

    protected function setUp(): void
    {
        parent::setUp();

        FixtureRegistrationField::reset();
        $this->registry()->clear();
    }

    protected function tearDown(): void
    {
        FixtureRegistrationField::reset();

        parent::tearDown();
    }

    public function test_available_fields_are_ordered_by_order_then_key(): void
    {
        $this->registry()->registerMany([
            $this->field('fixture.zeta', 5),
            $this->field('fixture.alpha', 5),
            $this->field('fixture.first', 1),
        ], 'fixture');

        $this->assertSame(
            ['fixture.first', 'fixture.alpha', 'fixture.zeta'],
            array_map(static fn (RegistrationFieldDTO $field): string => $field->key, $this->registry()->available()),
        );
    }

    public function test_an_available_field_carries_what_the_form_needs(): void
    {
        FixtureRegistrationField::$rules = ['accepted'];
        $this->registry()->register(FixtureRegistrationField::class, 'fixture');

        $field = $this->registry()->available()[0];

        $this->assertSame(FixtureRegistrationField::KEY, $field->key);
        $this->assertSame('Quiero recibir el boletín', $field->label);
        $this->assertSame('Puedes darte de baja cuando quieras.', $field->description);
        $this->assertSame(['accepted'], $field->rules);
    }

    public function test_the_page_gets_no_validation_rules(): void
    {
        FixtureRegistrationField::$rules = ['accepted'];
        $this->registry()->register(FixtureRegistrationField::class, 'fixture');

        $this->assertSame([
            'key' => FixtureRegistrationField::KEY,
            'label' => 'Quiero recibir el boletín',
            'description' => 'Puedes darte de baja cuando quieras.',
            'type' => 'checkbox',
        ], $this->registry()->available()[0]->toArray());
    }

    public function test_an_unavailable_field_is_left_out(): void
    {
        FixtureRegistrationField::$available = false;
        $this->registry()->register(FixtureRegistrationField::class, 'fixture');

        $this->assertSame([], $this->registry()->available());
    }

    public function test_a_field_whose_availability_throws_is_left_out_and_logged(): void
    {
        $log = Log::spy();
        FixtureRegistrationField::$availableFailsWith = new RuntimeException('Provider unreachable');
        $this->registry()->registerMany([FixtureRegistrationField::class, $this->field('fixture.other')], 'fixture');

        $this->assertSame(
            ['fixture.other'],
            array_map(static fn (RegistrationFieldDTO $field): string => $field->key, $this->registry()->available()),
        );
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'Failed to read registration field')
                && $context['field'] === FixtureRegistrationField::class
                && $context['module'] === 'fixture',
        )->once();
    }

    public function test_a_field_whose_rules_throw_is_left_out(): void
    {
        FixtureRegistrationField::$rulesFailWith = new RuntimeException('Broken rules');
        $this->registry()->register(FixtureRegistrationField::class, 'fixture');

        $this->assertSame([], $this->registry()->available());
    }

    public function test_a_class_the_container_cannot_build_is_left_out(): void
    {
        $name = $this->field('fixture.unbuildable');
        $this->app->bind($name, static fn (): never => throw new RuntimeException('Missing dependency'));
        $this->registry()->registerMany([$name, FixtureRegistrationField::class], 'fixture');

        $this->assertSame([FixtureRegistrationField::KEY], $this->keys());
    }

    public function test_a_class_that_is_not_a_registration_field_is_left_out(): void
    {
        $this->registry()->register(stdClass::class, 'fixture');

        $this->assertSame([], $this->registry()->available());
    }

    public function test_a_key_without_the_module_prefix_is_left_out(): void
    {
        $this->registry()->registerMany([
            $this->field('other.newsletter'),
            $this->field('core.newsletter'),
            $this->field('fixture'),
            $this->field('fixture.Newsletter'),
            $this->field('fixture.news.letter'),
            $this->field('fixture.ok'),
        ], 'fixture');

        $this->assertSame(['fixture.ok'], $this->keys());
    }

    public function test_core_fields_start_with_core(): void
    {
        $this->registry()->registerMany([$this->field('core.terms'), $this->field('fixture.terms')]);

        $this->assertSame(['core.terms'], $this->keys());
    }

    public function test_the_first_field_registered_keeps_a_repeated_key(): void
    {
        $first = $this->field('fixture.newsletter', 1, label: 'Primero');
        $second = $this->field('fixture.newsletter', 0, label: 'Segundo');
        $this->registry()->registerMany([$first, $second], 'fixture');

        $available = $this->registry()->available();

        $this->assertCount(1, $available);
        $this->assertSame('Primero', $available[0]->label);
    }

    public function test_an_empty_label_or_a_rule_list_that_is_not_a_list_leaves_the_field_out(): void
    {
        $this->registry()->registerMany([
            $this->field('fixture.blank', label: '  '),
            $this->field('fixture.keyed', rules: static fn (): array => ['value' => 'boolean']),
            $this->field('fixture.ok'),
        ], 'fixture');

        $this->assertSame(['fixture.ok'], $this->keys());
    }

    public function test_unregistering_a_module_drops_its_fields(): void
    {
        $this->registry()->register(FixtureRegistrationField::class, 'fixture');
        $this->registry()->register($this->field('other-module.terms'), 'other-module');

        $this->registry()->unregisterModule('fixture');

        $this->assertSame(['other-module.terms'], $this->keys());
    }

    public function test_the_module_hook_registers_fields_under_the_module(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'fixture';
            }

            public function registerRegistrationFields(): array
            {
                return [FixtureRegistrationField::class];
            }
        };

        $loader = new ModuleLoader($this->app, Mockery::mock(EnabledModulesResolverInterface::class));
        (new ReflectionMethod($loader, 'registerProviderRegistrationFields'))
            ->invoke($loader, $provider, $provider->moduleName());

        $this->assertSame([FixtureRegistrationField::class], $this->registry()->all());
        $this->assertSame([FixtureRegistrationField::KEY], $this->keys());
    }

    public function test_the_base_provider_registers_no_fields(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'fixture';
            }
        };

        $this->assertSame([], $provider->registerRegistrationFields());
    }

    private function registry(): RegistrationFieldRegistryInterface
    {
        return app(RegistrationFieldRegistryInterface::class);
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        return array_map(static fn (RegistrationFieldDTO $field): string => $field->key, $this->registry()->available());
    }

    /**
     * Registers a field with the given shape under a class name of its own (an alias the
     * container resolves to this instance).
     *
     * @param  (Closure(): array<mixed>)|null  $rules
     * @return class-string
     */
    private function field(string $key, int $order = 0, string $label = 'Casilla', ?Closure $rules = null): string
    {
        /** @var class-string $name */
        $name = ShapedRegistrationField::class.'Alias'.$this->fields++;

        if (! class_exists($name)) {
            class_alias(ShapedRegistrationField::class, $name);
        }

        $this->app->instance($name, new ShapedRegistrationField($key, $order, $label, $rules ?? static fn (): array => []));

        return $name;
    }
}

final readonly class ShapedRegistrationField implements RegistrationFieldInterface
{
    /**
     * @param  Closure(): array<mixed>  $rules
     */
    public function __construct(
        private string $key,
        private int $order,
        private string $label,
        private Closure $rules,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): ?string
    {
        return null;
    }

    public function type(): RegistrationFieldType
    {
        return RegistrationFieldType::Checkbox;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var array<int, mixed> */
        return ($this->rules)();
    }
}
