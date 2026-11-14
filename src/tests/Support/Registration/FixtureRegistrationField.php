<?php

declare(strict_types=1);

namespace Tests\Support\Registration;

use App\Application\Registration\Contracts\RegistrationFieldInterface;
use App\Domain\Registration\Enums\RegistrationFieldType;
use Throwable;

/**
 * A registration field like the newsletter module's «Quiero recibir el boletín» would be
 * (core#699), registered under the module «fixture». Its knobs are static so tests can turn it
 * off or make it throw; call reset() in setUp() and tearDown().
 */
final class FixtureRegistrationField implements RegistrationFieldInterface
{
    public const string KEY = 'fixture.newsletter';

    public static bool $available = true;

    public static ?Throwable $availableFailsWith = null;

    public static ?Throwable $rulesFailWith = null;

    /** @var array<int, mixed> */
    public static array $rules = [];

    public static ?string $description = 'Puedes darte de baja cuando quieras.';

    public static function reset(): void
    {
        self::$available = true;
        self::$availableFailsWith = null;
        self::$rulesFailWith = null;
        self::$rules = [];
        self::$description = 'Puedes darte de baja cuando quieras.';
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Quiero recibir el boletín';
    }

    public function description(): ?string
    {
        return self::$description;
    }

    public function type(): RegistrationFieldType
    {
        return RegistrationFieldType::Checkbox;
    }

    public function order(): int
    {
        return 10;
    }

    public function isAvailable(): bool
    {
        if (self::$availableFailsWith instanceof Throwable) {
            throw self::$availableFailsWith;
        }

        return self::$available;
    }

    public function rules(): array
    {
        if (self::$rulesFailWith instanceof Throwable) {
            throw self::$rulesFailWith;
        }

        return self::$rules;
    }
}
