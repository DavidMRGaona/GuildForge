<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\AnonymizeUserDTO;
use PHPUnit\Framework\TestCase;

final class AnonymizeUserDTOTest extends TestCase
{
    public function test_it_creates_dto_for_transfer_action(): void
    {
        $userId = 'user-123';
        $transferToUserId = 'user-456';

        $dto = new AnonymizeUserDTO(
            userId: $userId,
            contentAction: 'transfer',
            transferToUserId: $transferToUserId,
        );

        $this->assertEquals($userId, $dto->userId);
        $this->assertEquals('transfer', $dto->contentAction);
        $this->assertEquals($transferToUserId, $dto->transferToUserId);
    }

    public function test_it_creates_dto_for_anonymize_action(): void
    {
        $userId = 'user-123';

        $dto = new AnonymizeUserDTO(
            userId: $userId,
            contentAction: 'anonymize',
            transferToUserId: null,
        );

        $this->assertEquals($userId, $dto->userId);
        $this->assertEquals('anonymize', $dto->contentAction);
        $this->assertNull($dto->transferToUserId);
    }

    public function test_it_creates_dto_from_array_for_transfer(): void
    {
        $data = [
            'user_id' => 'user-789',
            'content_action' => 'transfer',
            'transfer_to_user_id' => 'user-012',
        ];

        $dto = AnonymizeUserDTO::fromArray($data);

        $this->assertEquals('user-789', $dto->userId);
        $this->assertEquals('transfer', $dto->contentAction);
        $this->assertEquals('user-012', $dto->transferToUserId);
    }

    public function test_it_creates_dto_from_array_for_anonymize(): void
    {
        $data = [
            'user_id' => 'user-789',
            'content_action' => 'anonymize',
        ];

        $dto = AnonymizeUserDTO::fromArray($data);

        $this->assertEquals('user-789', $dto->userId);
        $this->assertEquals('anonymize', $dto->contentAction);
        $this->assertNull($dto->transferToUserId);
    }
}
