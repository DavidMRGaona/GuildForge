<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\User;
use App\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_it_creates_user_with_required_data(): void
    {
        $id = UserId::generate();
        $name = 'John Doe';
        $email = 'john.doe@example.com';

        $user = new User(
            id: $id,
            name: $name,
            email: $email,
        );

        $this->assertEquals($id, $user->id());
        $this->assertEquals($name, $user->name());
        $this->assertEquals($email, $user->email());
        $this->assertNull($user->displayName());
        $this->assertNull($user->pendingEmail());
        $this->assertNull($user->avatarPublicId());
        $this->assertNull($user->emailVerifiedAt());
        $this->assertNull($user->anonymizedAt());
        $this->assertNull($user->createdAt());
        $this->assertNull($user->updatedAt());
    }

    public function test_it_creates_user_with_all_data(): void
    {
        $id = UserId::generate();
        $name = 'Jane Smith';
        $email = 'jane.smith@example.com';
        $displayName = 'JaneTheWarrior';
        $pendingEmail = 'jane.new@example.com';
        $avatarPublicId = 'users/avatars/jane-avatar.jpg';
        $emailVerifiedAt = new DateTimeImmutable('2024-01-10 14:00:00');
        $anonymizedAt = null;
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2024-01-15 12:30:00');

        $user = new User(
            id: $id,
            name: $name,
            email: $email,
            displayName: $displayName,
            pendingEmail: $pendingEmail,
            avatarPublicId: $avatarPublicId,
            emailVerifiedAt: $emailVerifiedAt,
            anonymizedAt: $anonymizedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->assertEquals($id, $user->id());
        $this->assertEquals($name, $user->name());
        $this->assertEquals($email, $user->email());
        $this->assertEquals($displayName, $user->displayName());
        $this->assertEquals($pendingEmail, $user->pendingEmail());
        $this->assertEquals($avatarPublicId, $user->avatarPublicId());
        $this->assertEquals($emailVerifiedAt, $user->emailVerifiedAt());
        $this->assertEquals($anonymizedAt, $user->anonymizedAt());
        $this->assertEquals($createdAt, $user->createdAt());
        $this->assertEquals($updatedAt, $user->updatedAt());
    }

    public function test_is_email_verified_returns_true_when_verified(): void
    {
        $user = $this->createUser(
            emailVerifiedAt: new DateTimeImmutable('2024-01-10 14:00:00'),
        );

        $this->assertTrue($user->isEmailVerified());
    }

    public function test_is_email_verified_returns_false_when_not_verified(): void
    {
        $user = $this->createUser(
            emailVerifiedAt: null,
        );

        $this->assertFalse($user->isEmailVerified());
    }

    public function test_is_anonymized_returns_true_when_anonymized(): void
    {
        $user = $this->createUser(
            anonymizedAt: new DateTimeImmutable('2024-06-15 10:00:00'),
        );

        $this->assertTrue($user->isAnonymized());
    }

    public function test_is_anonymized_returns_false_when_not_anonymized(): void
    {
        $user = $this->createUser(
            anonymizedAt: null,
        );

        $this->assertFalse($user->isAnonymized());
    }

    public function test_get_displayable_name_returns_display_name_when_set(): void
    {
        $name = 'John Doe';
        $displayName = 'JohnTheConqueror';

        $user = $this->createUser(
            name: $name,
            displayName: $displayName,
        );

        $this->assertEquals($displayName, $user->getDisplayableName());
    }

    public function test_get_displayable_name_returns_name_when_display_name_not_set(): void
    {
        $name = 'Jane Smith';

        $user = $this->createUser(
            name: $name,
            displayName: null,
        );

        $this->assertEquals($name, $user->getDisplayableName());
    }

    private function createUser(
        ?string $name = null,
        ?string $email = null,
        ?string $displayName = null,
        ?DateTimeImmutable $emailVerifiedAt = null,
        ?DateTimeImmutable $anonymizedAt = null,
    ): User {
        return new User(
            id: UserId::generate(),
            name: $name ?? 'Test User',
            email: $email ?? 'test@example.com',
            displayName: $displayName,
            emailVerifiedAt: $emailVerifiedAt,
            anonymizedAt: $anonymizedAt,
        );
    }
}
