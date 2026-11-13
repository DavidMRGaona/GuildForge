<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Services;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Domain\Proposals\Enums\ProposalRequestFieldType;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EventProposalRequestRegistry implements EventProposalRequestRegistryInterface
{
    private const string CORE_PREFIX = 'core';

    /** @var array<class-string, ?string> request class => owning module (null for the core) */
    private array $requests = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(string $requestClass, ?string $module = null): void
    {
        $this->requests[$requestClass] = $module;
    }

    public function registerMany(array $requestClasses, ?string $module = null): void
    {
        foreach ($requestClasses as $requestClass) {
            $this->register($requestClass, $module);
        }
    }

    public function all(): array
    {
        return array_keys($this->requests);
    }

    public function requests(): array
    {
        $resolved = [];
        $orders = [];

        foreach ($this->requests as $requestClass => $module) {
            $request = $this->resolve($requestClass, $module);

            if (! $request instanceof EventProposalRequestInterface) {
                continue;
            }

            $read = $this->read($request, $module);

            if ($read === null) {
                continue;
            }

            [$key, $order] = $read;

            if (isset($resolved[$key])) {
                $this->warn('Request key already taken, request skipped', $requestClass, $module, ['key' => $key, 'takenBy' => $resolved[$key]::class]);

                continue;
            }

            $resolved[$key] = $request;
            $orders[$key] = $order;
        }

        uksort($resolved, static fn (string $a, string $b): int => [$orders[$a], $a] <=> [$orders[$b], $b]);

        return $resolved;
    }

    public function request(string $key): ?EventProposalRequestInterface
    {
        return $this->requests()[$key] ?? null;
    }

    public function unregisterModule(string $module): void
    {
        $this->requests = array_filter(
            $this->requests,
            static fn (?string $requestModule): bool => $requestModule !== $module,
        );
    }

    public function clear(): void
    {
        $this->requests = [];
    }

    /**
     * @param  class-string  $requestClass
     */
    private function resolve(string $requestClass, ?string $module): ?EventProposalRequestInterface
    {
        try {
            $request = $this->container->make($requestClass);
        } catch (Throwable $exception) {
            $this->warn('Failed to resolve event proposal request', $requestClass, $module, ['error' => $exception->getMessage()]);

            return null;
        }

        if (! $request instanceof EventProposalRequestInterface) {
            $this->warn('Registered class is not an event proposal request', $requestClass, $module);

            return null;
        }

        return $request;
    }

    /**
     * The request's key and order, once its key and fields are known to be usable.
     *
     * @return ?array{0: string, 1: int}
     */
    private function read(EventProposalRequestInterface $request, ?string $module): ?array
    {
        try {
            $key = $request->key();
            $order = $request->order();
            $fields = $request->fields();
        } catch (Throwable $exception) {
            $this->warn('Failed to read event proposal request', $request::class, $module, ['error' => $exception->getMessage()]);

            return null;
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1 || ! str_starts_with($key, ($module ?? self::CORE_PREFIX).'.')) {
            $this->warn('Request key must be "<module>.<kebab-case name>", request skipped', $request::class, $module, ['key' => $key]);

            return null;
        }

        $problem = $this->fieldsProblem($fields);

        if ($problem !== null) {
            $this->warn('Request fields are not usable, request skipped', $request::class, $module, ['key' => $key, 'problem' => $problem]);

            return null;
        }

        return [$key, $order];
    }

    /**
     * Why the fields cannot be used, or null when they can: every one a field, names unique and
     * each dependency on an earlier field, with values of that field's type.
     */
    private function fieldsProblem(mixed $fields): ?string
    {
        if (! is_array($fields) || ! array_is_list($fields)) {
            return 'fields() must return a list';
        }

        $seen = [];

        foreach ($fields as $field) {
            if (! $field instanceof ProposalRequestFieldDTO) {
                return 'every field must be a '.ProposalRequestFieldDTO::class;
            }

            if (isset($seen[$field->name])) {
                return sprintf('field [%s] is repeated', $field->name);
            }

            if ($field->visibleWhenField !== null) {
                $controller = $seen[$field->visibleWhenField] ?? null;

                if (! $controller instanceof ProposalRequestFieldDTO) {
                    return sprintf('field [%s] depends on [%s], which is not an earlier field', $field->name, $field->visibleWhenField);
                }

                foreach ($field->visibleWhenValues ?? [] as $value) {
                    if (! $this->fits($controller, $value)) {
                        return sprintf('field [%s] depends on a value [%s] cannot hold', $field->name, $controller->name);
                    }
                }
            }

            $seen[$field->name] = $field;
        }

        return null;
    }

    private function fits(ProposalRequestFieldDTO $field, bool|int|string $value): bool
    {
        return match ($field->type) {
            ProposalRequestFieldType::Boolean => is_bool($value),
            ProposalRequestFieldType::Number => is_int($value),
            ProposalRequestFieldType::Select, ProposalRequestFieldType::Text => is_string($value),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warn(string $message, string $requestClass, ?string $module, array $context = []): void
    {
        Log::warning('[EventProposalRequestRegistry] '.$message, [
            'request' => $requestClass,
            'module' => $module,
            ...$context,
        ]);
    }
}
