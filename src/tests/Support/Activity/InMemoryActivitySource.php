<?php

declare(strict_types=1);

namespace Tests\Support\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Domain\Activity\ActivityKind;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * A core activity source whose subjects live in memory, as a module's would live in its tables:
 * an example of the contract and what ActivitySourceContractTests checks it against.
 */
final class InMemoryActivitySource implements ActivitySourceInterface, OffersActivityAttributes
{
    public const string TYPE = 'core.in-memory';

    /** @var array<string, list<ActivityFactDTO>> */
    private static array $subjects = [];

    /**
     * @param  list<ActivityFactDTO>  $facts
     */
    public static function put(string $subjectId, array $facts): void
    {
        self::$subjects[$subjectId] = $facts;
        ksort(self::$subjects, SORT_STRING);
    }

    public static function reset(): void
    {
        self::$subjects = [];
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::GameSession;
    }

    public function label(): string
    {
        return 'Partidas de prueba';
    }

    public function facts(string $subjectId): array
    {
        return self::$subjects[$subjectId] ?? [];
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        // The cursor is the last id of the previous page: opaque to the caller
        $after = null;
        if ($cursor !== null) {
            $after = base64_decode($cursor, true);

            // Never start again from the first page: the reader reads a throw as failed
            if ($after === false || $after === '') {
                throw new InvalidArgumentException('Unreadable activity history cursor.');
            }
        }

        $ids = array_values(array_filter(
            array_map(strval(...), array_keys(self::$subjects)),
            static fn (string $id): bool => $after === null || strcmp($id, $after) > 0,
        ));
        $page = array_slice($ids, 0, $limit);
        $more = count($ids) > $limit;

        return new ActivityHistoryPageDTO(
            array_map(static fn (string $id): ActivitySubjectFactsDTO => new ActivitySubjectFactsDTO($id, self::$subjects[$id]), $page),
            $more ? base64_encode((string) end($page)) : null,
        );
    }

    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        $described = [];
        foreach ($subjectIds as $id) {
            if (isset(self::$subjects[$id])) {
                $described[$id] = new ActivitySubjectDTO($id, 'Partida '.$id, '/partidas/'.$id);
            }
        }

        return $described;
    }

    public function attributes(): array
    {
        return [new ActivityAttributeDTO('game_system', 'Sistema de juego')];
    }

    public function attributeValues(string $key): array
    {
        return $key === 'game_system' ? [new ActivityAttributeValueDTO('pf2', 'Pathfinder 2'), new ActivityAttributeValueDTO('dnd5', 'D&D 5')] : [];
    }
}
