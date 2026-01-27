<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Modules\DTOs;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleRouteDTOTest extends TestCase
{
    #[Test]
    public function it_creates_module_route_dto_from_constructor(): void
    {
        $dto = new ModuleRouteDTO(
            routeName: 'gametables.index',
            label: 'Mesas de rol',
            module: 'game-tables',
        );

        $this->assertSame('gametables.index', $dto->routeName);
        $this->assertSame('Mesas de rol', $dto->label);
        $this->assertSame('game-tables', $dto->module);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'routeName' => 'tournaments.index',
            'label' => 'Torneos',
            'module' => 'tournaments',
        ];

        $dto = ModuleRouteDTO::fromArray($data);

        $this->assertSame('tournaments.index', $dto->routeName);
        $this->assertSame('Torneos', $dto->label);
        $this->assertSame('tournaments', $dto->module);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $dto = new ModuleRouteDTO(
            routeName: 'rankings.index',
            label: 'Rankings',
            module: 'rankings',
        );

        $expected = [
            'routeName' => 'rankings.index',
            'label' => 'Rankings',
            'module' => 'rankings',
        ];

        $this->assertSame($expected, $dto->toArray());
    }
}
