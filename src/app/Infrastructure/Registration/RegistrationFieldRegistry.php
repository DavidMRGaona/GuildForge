<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use App\Application\Registration\Contracts\RegistrationFieldInterface;
use App\Application\Registration\DTOs\RegistrationFieldDTO;
use App\Application\Registration\Services\RegistrationFieldRegistryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RegistrationFieldRegistry implements RegistrationFieldRegistryInterface
{
    private const string CORE_PREFIX = 'core';

    /** @var array<class-string, ?string> field class => owning module (null for the core) */
    private array $fields = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(string $fieldClass, ?string $module = null): void
    {
        $this->fields[$fieldClass] = $module;
    }

    public function registerMany(array $fieldClasses, ?string $module = null): void
    {
        foreach ($fieldClasses as $fieldClass) {
            $this->register($fieldClass, $module);
        }
    }

    public function all(): array
    {
        return array_keys($this->fields);
    }

    public function available(): array
    {
        /** @var array<string, RegistrationFieldDTO> $available */
        $available = [];
        /** @var array<string, int> $orders */
        $orders = [];
        /** @var array<string, class-string> $takenBy */
        $takenBy = [];

        foreach ($this->fields as $fieldClass => $module) {
            $field = $this->resolve($fieldClass, $module);

            if (! $field instanceof RegistrationFieldInterface) {
                continue;
            }

            $read = $this->read($field, $module);

            if ($read === null) {
                continue;
            }

            [$key, $order, $dto] = $read;

            if (isset($takenBy[$key])) {
                $this->warn('Registration field key already taken, field skipped', $fieldClass, $module, ['key' => $key, 'takenBy' => $takenBy[$key]]);

                continue;
            }

            // Taken even when unavailable now, so a later field cannot take the key over
            $takenBy[$key] = $fieldClass;

            if ($dto instanceof RegistrationFieldDTO) {
                $available[$key] = $dto;
                $orders[$key] = $order;
            }
        }

        uksort($available, static fn (string $a, string $b): int => [$orders[$a], $a] <=> [$orders[$b], $b]);

        return array_values($available);
    }

    public function unregisterModule(string $module): void
    {
        $this->fields = array_filter(
            $this->fields,
            static fn (?string $fieldModule): bool => $fieldModule !== $module,
        );
    }

    public function clear(): void
    {
        $this->fields = [];
    }

    /**
     * @param  class-string  $fieldClass
     */
    private function resolve(string $fieldClass, ?string $module): ?RegistrationFieldInterface
    {
        try {
            $field = $this->container->make($fieldClass);
        } catch (Throwable $exception) {
            $this->warn('Failed to resolve registration field', $fieldClass, $module, ['exception' => $exception::class]);

            return null;
        }

        if (! $field instanceof RegistrationFieldInterface) {
            $this->warn('Registered class is not a registration field', $fieldClass, $module);

            return null;
        }

        return $field;
    }

    /**
     * The field's key and order, with what the form needs when it is available now (null when it
     * is not). Null altogether when the field cannot be used.
     *
     * @return ?array{0: string, 1: int, 2: ?RegistrationFieldDTO}
     */
    private function read(RegistrationFieldInterface $field, ?string $module): ?array
    {
        try {
            $key = $field->key();

            if (preg_match(self::KEY_PATTERN, $key) !== 1 || ! str_starts_with($key, ($module ?? self::CORE_PREFIX).'.')) {
                $this->warn('Registration field key must be "<module>.<kebab-case name>", field skipped', $field::class, $module, ['key' => $key]);

                return null;
            }

            $order = $field->order();

            if (! $field->isAvailable()) {
                return [$key, $order, null];
            }

            $label = trim($field->label());
            $description = $field->description();
            $rules = $field->rules();
            $type = $field->type();
        } catch (Throwable $exception) {
            $this->warn('Failed to read registration field, field skipped', $field::class, $module, ['exception' => $exception::class]);

            return null;
        }

        if ($label === '' || ! array_is_list($rules)) {
            $this->warn('Registration field needs a label and a list of rules, field skipped', $field::class, $module, ['key' => $key]);

            return null;
        }

        $description = $description === null || trim($description) === '' ? null : trim($description);

        return [$key, $order, new RegistrationFieldDTO($key, $label, $description, $type, $rules)];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warn(string $message, string $fieldClass, ?string $module, array $context = []): void
    {
        Log::warning('[RegistrationFieldRegistry] '.$message, [
            'field' => $fieldClass,
            'module' => $module,
            ...$context,
        ]);
    }
}
