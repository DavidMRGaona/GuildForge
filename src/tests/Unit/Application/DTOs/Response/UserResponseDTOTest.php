<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs\Response;

use App\Application\DTOs\Response\UserResponseDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserResponseDTOTest extends TestCase
{
    public function test_it_serializes_the_account_data(): void
    {
        $dto = new UserResponseDTO(
            id: 'user-id',
            name: 'Ana',
            displayName: 'Ana G.',
            email: 'ana@example.com',
            pendingEmail: null,
            avatarPublicId: 'avatars/ana.jpg',
            emailVerified: true,
            createdAt: new DateTimeImmutable('2026-10-14T10:00:00+00:00'),
        );

        $this->assertSame([
            'id' => 'user-id',
            'name' => 'Ana',
            'displayName' => 'Ana G.',
            'email' => 'ana@example.com',
            'pendingEmail' => null,
            'avatarPublicId' => 'avatars/ana.jpg',
            'emailVerified' => true,
            'createdAt' => '2026-10-14T10:00:00+00:00',
        ], $dto->jsonSerialize());
    }
}
