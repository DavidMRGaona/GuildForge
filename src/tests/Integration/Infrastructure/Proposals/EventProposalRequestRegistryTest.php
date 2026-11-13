<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Proposals;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Domain\ValueObjects\EventId;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\Proposals\FixtureEventProposalRequest;
use Tests\TestCase;

final class EventProposalRequestRegistryTest extends TestCase
{
    private int $requests = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry()->clear();
    }

    private function registry(): EventProposalRequestRegistryInterface
    {
        return app(EventProposalRequestRegistryInterface::class);
    }

    /**
     * Registers a request with the given shape under a class name of its own (an alias the
     * container resolves to this instance).
     *
     * @param  \Closure(): array<mixed>|null  $fields
     * @return class-string
     */
    private function request(string $key, int $order = 0, ?\Closure $fields = null): string
    {
        /** @var class-string $name */
        $name = ShapedRegistryTestRequest::class.'Alias'.$this->requests++;

        if (! class_exists($name)) {
            class_alias(ShapedRegistryTestRequest::class, $name);
        }

        $this->app->instance($name, new ShapedRegistryTestRequest($key, $order, $fields ?? static fn (): array => []));

        return $name;
    }

    /**
     * A registered class the container cannot build.
     *
     * @return class-string
     */
    private function unbuildable(): string
    {
        $name = $this->request('fixture.unbuildable');
        $this->app->bind($name, static fn (): never => throw new RuntimeException('Missing dependency'));

        return $name;
    }

    public function test_requests_are_keyed_and_ordered_by_order_then_key(): void
    {
        $this->registry()->registerMany([
            $this->request('fixture.zeta', 5),
            $this->request('fixture.alpha', 5),
            $this->request('fixture.first', 1),
        ], 'fixture');

        $this->assertSame(['fixture.first', 'fixture.alpha', 'fixture.zeta'], array_keys($this->registry()->requests()));
        $this->assertSame('fixture.alpha', $this->registry()->request('fixture.alpha')?->key());
        $this->assertNull($this->registry()->request('fixture.missing'));
    }

    public function test_it_fails_closed_on_every_unusable_request(): void
    {
        $this->registry()->registerMany([
            $this->unbuildable(),
            \stdClass::class,
            $this->request('other-module.thing'),
            $this->request('fixture.Bad_Key'),
            $this->request('fixture'),
            $this->request('fixture.throws', fields: static fn (): array => throw new RuntimeException('broken')),
            $this->request('fixture.not-a-list', fields: static fn (): array => ['a' => ProposalRequestFieldDTO::boolean('a', 'A')]),
            $this->request('fixture.not-fields', fields: static fn (): array => ['nope']),
            $this->request('fixture.repeated', fields: static fn (): array => [ProposalRequestFieldDTO::boolean('a', 'A'), ProposalRequestFieldDTO::boolean('a', 'B')]),
            $this->request('fixture.later', fields: static fn (): array => [ProposalRequestFieldDTO::boolean('a', 'A')->visibleWhenFilled('b'), ProposalRequestFieldDTO::boolean('b', 'B')]),
            $this->request('fixture.wrong-type', fields: static fn (): array => [ProposalRequestFieldDTO::boolean('a', 'A'), ProposalRequestFieldDTO::text('b', 'B')->visibleWhen('a', 'yes')]),
            $this->request('fixture.good', fields: static fn (): array => [ProposalRequestFieldDTO::boolean('a', 'A'), ProposalRequestFieldDTO::text('b', 'B')->visibleWhen('a', true)]),
        ], 'fixture');

        $this->assertSame(['fixture.good'], array_keys($this->registry()->requests()));
    }

    public function test_the_first_request_registered_for_a_key_wins(): void
    {
        $first = $this->request('fixture.same');
        $this->registry()->registerMany([$first, $this->request('fixture.same')], 'fixture');

        $this->assertSame(app($first), $this->registry()->request('fixture.same'));
    }

    public function test_core_keys_start_with_core(): void
    {
        $this->registry()->registerMany([$this->request('core.thing'), $this->request('fixture.thing')]);

        $this->assertSame(['core.thing'], array_keys($this->registry()->requests()));
    }

    public function test_an_enabled_module_registers_its_requests_under_its_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'fixture';
            }

            public function registerEventProposalRequests(): array
            {
                return [FixtureEventProposalRequest::class];
            }
        };

        $loader = new ModuleLoader($this->app, Mockery::mock(EnabledModulesResolverInterface::class));
        (new ReflectionMethod($loader, 'registerProviderEventProposalRequests'))->invoke($loader, $provider, 'fixture');

        $this->assertSame([FixtureEventProposalRequest::class], $this->registry()->all());
        $this->assertSame([FixtureEventProposalRequest::KEY], array_keys($this->registry()->requests()));

        $this->registry()->unregisterModule('fixture');
        $this->assertSame([], $this->registry()->all());
    }
}

/**
 * A request whose key, order and fields come from the test.
 */
final readonly class ShapedRegistryTestRequest implements EventProposalRequestInterface
{
    /**
     * @param  \Closure(): array<mixed>  $fields
     */
    public function __construct(
        private string $key,
        private int $order,
        private \Closure $fields,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return 'Petición';
    }

    public function question(): string
    {
        return '¿Quieres esto?';
    }

    public function order(): int
    {
        return $this->order;
    }

    public function fields(): array
    {
        /** @var list<ProposalRequestFieldDTO> $fields */
        $fields = ($this->fields)();

        return $fields;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function apply(EventId $eventId, array $values): void {}

    public function describe(array $values): string
    {
        return 'Petición';
    }
}
