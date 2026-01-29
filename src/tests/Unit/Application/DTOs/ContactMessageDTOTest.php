<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\ContactMessageDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ContactMessageDTO::class)]
final class ContactMessageDTOTest extends TestCase
{
    #[Test]
    public function it_creates_dto_with_all_properties(): void
    {
        $dto = new ContactMessageDTO(
            senderName: 'Juan García',
            senderEmail: 'juan@example.com',
            messageBody: 'Hola, quiero más información.',
        );

        $this->assertSame('Juan García', $dto->senderName);
        $this->assertSame('juan@example.com', $dto->senderEmail);
        $this->assertSame('Hola, quiero más información.', $dto->messageBody);
    }

    #[Test]
    public function it_creates_dto_from_array(): void
    {
        $data = [
            'name' => 'María López',
            'email' => 'maria@example.com',
            'message' => 'Me gustaría saber más sobre los eventos.',
        ];

        $dto = ContactMessageDTO::fromArray($data);

        $this->assertSame('María López', $dto->senderName);
        $this->assertSame('maria@example.com', $dto->senderEmail);
        $this->assertSame('Me gustaría saber más sobre los eventos.', $dto->messageBody);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $reflection = new \ReflectionClass(ContactMessageDTO::class);

        $this->assertTrue($reflection->isReadOnly());
    }
}
