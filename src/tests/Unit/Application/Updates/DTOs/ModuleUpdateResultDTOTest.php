<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Updates\DTOs;

use App\Application\Updates\DTOs\ModuleUpdateResultDTO;
use App\Domain\Updates\Enums\UpdateStatus;
use PHPUnit\Framework\TestCase;

final class ModuleUpdateResultDTOTest extends TestCase
{
    public function test_it_creates_dto_with_all_properties(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'forum',
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
            status: UpdateStatus::Completed,
            migrationsRun: ['2024_01_01_create_posts_table.php', '2024_01_02_create_comments_table.php'],
            seedersRun: ['ForumSeeder'],
            errorMessage: null,
            backupPath: '/storage/backups/modules/forum/forum_1.0.0_2024-01-15_10-30-00.zip',
            historyId: 'abc-123-def-456',
        );

        $this->assertEquals('forum', $dto->moduleName);
        $this->assertEquals('1.0.0', $dto->fromVersion);
        $this->assertEquals('1.1.0', $dto->toVersion);
        $this->assertEquals(UpdateStatus::Completed, $dto->status);
        $this->assertCount(2, $dto->migrationsRun);
        $this->assertCount(1, $dto->seedersRun);
        $this->assertNull($dto->errorMessage);
        $this->assertStringContainsString('forum_1.0.0', $dto->backupPath);
        $this->assertEquals('abc-123-def-456', $dto->historyId);
    }

    public function test_is_success_returns_true_for_completed_status(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'shop',
            fromVersion: '2.0.0',
            toVersion: '2.1.0',
            status: UpdateStatus::Completed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: null,
            backupPath: null,
            historyId: 'history-1',
        );

        $this->assertTrue($dto->isSuccess());
    }

    public function test_is_success_returns_false_for_failed_status(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'shop',
            fromVersion: '2.0.0',
            toVersion: '2.1.0',
            status: UpdateStatus::Failed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: 'Migration failed',
            backupPath: null,
            historyId: 'history-1',
        );

        $this->assertFalse($dto->isSuccess());
    }

    public function test_is_success_returns_false_for_rolled_back_status(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'shop',
            fromVersion: '2.0.0',
            toVersion: '2.1.0',
            status: UpdateStatus::RolledBack,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: 'Health check failed',
            backupPath: '/backups/shop.zip',
            historyId: 'history-1',
        );

        $this->assertFalse($dto->isSuccess());
    }

    public function test_was_rolled_back_returns_true_for_rolled_back_status(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'events',
            fromVersion: '1.0.0',
            toVersion: '1.2.0',
            status: UpdateStatus::RolledBack,
            migrationsRun: ['2024_01_01_migration.php'],
            seedersRun: [],
            errorMessage: 'Provider failed to load',
            backupPath: '/backups/events.zip',
            historyId: 'history-2',
        );

        $this->assertTrue($dto->wasRolledBack());
    }

    public function test_was_rolled_back_returns_false_for_completed_status(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'events',
            fromVersion: '1.0.0',
            toVersion: '1.2.0',
            status: UpdateStatus::Completed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: null,
            backupPath: null,
            historyId: 'history-2',
        );

        $this->assertFalse($dto->wasRolledBack());
    }

    public function test_was_rolled_back_returns_false_for_failed_status(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'events',
            fromVersion: '1.0.0',
            toVersion: '1.2.0',
            status: UpdateStatus::Failed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: 'Download failed',
            backupPath: null,
            historyId: 'history-2',
        );

        $this->assertFalse($dto->wasRolledBack());
    }

    public function test_to_array_returns_correct_array_representation(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'gallery',
            fromVersion: '3.0.0',
            toVersion: '3.1.0',
            status: UpdateStatus::Completed,
            migrationsRun: ['create_albums_table.php'],
            seedersRun: ['AlbumSeeder'],
            errorMessage: null,
            backupPath: '/backups/gallery.zip',
            historyId: 'uuid-here',
        );

        $array = $dto->toArray();

        $this->assertEquals('gallery', $array['module_name']);
        $this->assertEquals('3.0.0', $array['from_version']);
        $this->assertEquals('3.1.0', $array['to_version']);
        $this->assertEquals('completed', $array['status']);
        $this->assertEquals(['create_albums_table.php'], $array['migrations_run']);
        $this->assertEquals(['AlbumSeeder'], $array['seeders_run']);
        $this->assertNull($array['error_message']);
        $this->assertEquals('/backups/gallery.zip', $array['backup_path']);
        $this->assertEquals('uuid-here', $array['history_id']);
    }

    public function test_to_array_includes_error_message_when_present(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'payments',
            fromVersion: '1.0.0',
            toVersion: '2.0.0',
            status: UpdateStatus::Failed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: 'Checksum mismatch',
            backupPath: null,
            historyId: 'failed-uuid',
        );

        $array = $dto->toArray();

        $this->assertEquals('Checksum mismatch', $array['error_message']);
        $this->assertEquals('failed', $array['status']);
    }

    public function test_dto_is_readonly(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'test',
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
            status: UpdateStatus::Completed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: null,
            backupPath: null,
            historyId: 'test-id',
        );

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_it_handles_empty_migrations_and_seeders(): void
    {
        $dto = new ModuleUpdateResultDTO(
            moduleName: 'minimal',
            fromVersion: '1.0.0',
            toVersion: '1.0.1',
            status: UpdateStatus::Completed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: null,
            backupPath: null,
            historyId: 'minimal-id',
        );

        $this->assertEmpty($dto->migrationsRun);
        $this->assertEmpty($dto->seedersRun);

        $array = $dto->toArray();
        $this->assertEquals([], $array['migrations_run']);
        $this->assertEquals([], $array['seeders_run']);
    }
}
