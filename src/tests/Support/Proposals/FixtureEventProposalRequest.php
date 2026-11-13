<?php

declare(strict_types=1);

namespace Tests\Support\Proposals;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\ValueObjects\EventId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * A module request like event-registrations' would be (core#581), registered under the module
 * «fixture». apply() writes its configuration to the settings table, so it rolls back with the
 * approval, and refuses when the event already has one, as a real module must.
 */
final class FixtureEventProposalRequest implements EventProposalRequestInterface
{
    public const string KEY = 'fixture.registrations';

    public static bool $available = true;

    public static ?Throwable $failWith = null;

    public static function reset(): void
    {
        self::$available = true;
        self::$failWith = null;
    }

    public static function configKey(string $eventId): string
    {
        return 'fixture_registrations:'.$eventId;
    }

    /**
     * @return ?array<string, bool|int|string|null>
     */
    public static function configOf(string $eventId): ?array
    {
        $value = DB::table('settings')->where('key', self::configKey($eventId))->value('value');

        /** @var ?array<string, bool|int|string|null> $config */
        $config = is_string($value) ? json_decode($value, true) : null;

        return $config;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Inscripciones';
    }

    public function question(): string
    {
        return '¿Quieres inscripciones?';
    }

    public function order(): int
    {
        return 10;
    }

    public function fields(): array
    {
        return [
            ProposalRequestFieldDTO::number('capacity', 'Aforo', min: 1, max: 500, helperText: 'Vacío para no limitarlo'),
            ProposalRequestFieldDTO::boolean('waitlist', 'Lista de espera')->visibleWhenFilled('capacity'),
            ProposalRequestFieldDTO::select('mode', 'Quién se inscribe', ['open' => 'Cualquiera', 'members' => 'Solo socios'], required: true, default: 'open'),
            ProposalRequestFieldDTO::text('note', 'Nota', maxLength: 100),
        ];
    }

    public function isAvailable(): bool
    {
        return self::$available;
    }

    public function apply(EventId $eventId, array $values): void
    {
        if (self::$failWith instanceof Throwable) {
            throw self::$failWith;
        }

        if (self::configOf($eventId->value()) !== null) {
            throw ProposalRequestRefusedException::because('El evento ya tiene inscripciones.');
        }

        DB::table('settings')->insert([
            'id' => (string) Str::uuid(),
            'key' => self::configKey($eventId->value()),
            'value' => json_encode($values),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function describe(array $values): string
    {
        $parts = ['Inscripciones'];
        $parts[] = is_int($values['capacity'] ?? null) ? 'aforo de '.$values['capacity'] : 'sin límite';

        if (($values['waitlist'] ?? false) === true) {
            $parts[] = 'con lista de espera';
        }

        return implode(', ', $parts);
    }
}
