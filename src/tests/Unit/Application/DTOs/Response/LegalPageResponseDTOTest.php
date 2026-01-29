<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs\Response;

use App\Application\DTOs\Response\LegalPageResponseDTO;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(LegalPageResponseDTO::class)]
final class LegalPageResponseDTOTest extends TestCase
{
    #[Test]
    public function it_creates_dto_with_all_properties(): void
    {
        $lastUpdated = new DateTimeImmutable('2024-01-15 10:30:00');

        $dto = new LegalPageResponseDTO(
            title: 'Política de privacidad',
            content: '<p>Contenido legal aquí</p>',
            lastUpdated: $lastUpdated,
        );

        $this->assertSame('Política de privacidad', $dto->title);
        $this->assertSame('<p>Contenido legal aquí</p>', $dto->content);
        $this->assertSame($lastUpdated, $dto->lastUpdated);
    }

    #[Test]
    public function it_creates_dto_with_null_last_updated(): void
    {
        $dto = new LegalPageResponseDTO(
            title: 'Aviso legal',
            content: '<p>Contenido del aviso</p>',
            lastUpdated: null,
        );

        $this->assertSame('Aviso legal', $dto->title);
        $this->assertSame('<p>Contenido del aviso</p>', $dto->content);
        $this->assertNull($dto->lastUpdated);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $reflection = new \ReflectionClass(LegalPageResponseDTO::class);

        $this->assertTrue($reflection->isReadOnly());
    }
}
