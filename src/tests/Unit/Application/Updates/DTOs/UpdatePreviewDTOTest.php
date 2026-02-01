<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Updates\DTOs;

use App\Application\Updates\DTOs\UpdatePreviewDTO;
use PHPUnit\Framework\TestCase;

final class UpdatePreviewDTOTest extends TestCase
{
    public function test_it_creates_dto_with_all_properties(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'forum',
            fromVersion: '1.0.0',
            toVersion: '2.0.0',
            pendingMigrations: ['create_posts_table.php', 'create_comments_table.php'],
            newSeeders: ['ForumSeeder'],
            changelog: '## v2.0.0\n\n- Added comments feature\n- Improved performance',
            isMajorUpdate: true,
            coreCompatible: true,
            coreRequirement: '^1.5',
            downloadUrl: 'https://github.com/owner/repo/releases/download/v2.0.0/forum.zip',
            downloadSize: 1048576,
        );

        $this->assertEquals('forum', $dto->moduleName);
        $this->assertEquals('1.0.0', $dto->fromVersion);
        $this->assertEquals('2.0.0', $dto->toVersion);
        $this->assertCount(2, $dto->pendingMigrations);
        $this->assertCount(1, $dto->newSeeders);
        $this->assertStringContainsString('v2.0.0', $dto->changelog);
        $this->assertTrue($dto->isMajorUpdate);
        $this->assertTrue($dto->coreCompatible);
        $this->assertEquals('^1.5', $dto->coreRequirement);
        $this->assertStringContainsString('v2.0.0', $dto->downloadUrl);
        $this->assertEquals(1048576, $dto->downloadSize);
    }

    public function test_has_migrations_returns_true_when_migrations_exist(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'shop',
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
            pendingMigrations: ['add_products_table.php'],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $this->assertTrue($dto->hasMigrations());
    }

    public function test_has_migrations_returns_false_when_no_migrations(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'shop',
            fromVersion: '1.0.0',
            toVersion: '1.0.1',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $this->assertFalse($dto->hasMigrations());
    }

    public function test_has_seeders_returns_true_when_seeders_exist(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'events',
            fromVersion: '2.0.0',
            toVersion: '2.1.0',
            pendingMigrations: [],
            newSeeders: ['EventCategoriesSeeder', 'DefaultEventsSeeder'],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $this->assertTrue($dto->hasSeeders());
    }

    public function test_has_seeders_returns_false_when_no_seeders(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'events',
            fromVersion: '2.0.0',
            toVersion: '2.0.1',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $this->assertFalse($dto->hasSeeders());
    }

    public function test_has_breaking_changes_returns_true_for_major_update(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'gallery',
            fromVersion: '1.5.0',
            toVersion: '2.0.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: true,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $this->assertTrue($dto->hasBreakingChanges());
    }

    public function test_has_breaking_changes_returns_true_when_core_incompatible(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'gallery',
            fromVersion: '1.5.0',
            toVersion: '1.6.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: false,
            coreRequirement: '^2.0',
            downloadUrl: null,
            downloadSize: null,
        );

        $this->assertTrue($dto->hasBreakingChanges());
    }

    public function test_has_breaking_changes_returns_false_for_minor_compatible_update(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'gallery',
            fromVersion: '1.5.0',
            toVersion: '1.6.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: '^1.0',
            downloadUrl: null,
            downloadSize: null,
        );

        $this->assertFalse($dto->hasBreakingChanges());
    }

    public function test_has_breaking_changes_returns_true_when_both_major_and_incompatible(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'payments',
            fromVersion: '1.0.0',
            toVersion: '2.0.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: true,
            coreCompatible: false,
            coreRequirement: '^3.0',
            downloadUrl: null,
            downloadSize: null,
        );

        $this->assertTrue($dto->hasBreakingChanges());
    }

    public function test_to_array_returns_correct_representation(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'notifications',
            fromVersion: '1.0.0',
            toVersion: '1.2.0',
            pendingMigrations: ['migration1.php', 'migration2.php'],
            newSeeders: ['NotificationSeeder'],
            changelog: 'New features and fixes',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: '^1.0',
            downloadUrl: 'https://example.com/download.zip',
            downloadSize: 512000,
        );

        $array = $dto->toArray();

        $this->assertEquals('notifications', $array['module_name']);
        $this->assertEquals('1.0.0', $array['from_version']);
        $this->assertEquals('1.2.0', $array['to_version']);
        $this->assertEquals(['migration1.php', 'migration2.php'], $array['pending_migrations']);
        $this->assertEquals(['NotificationSeeder'], $array['new_seeders']);
        $this->assertEquals('New features and fixes', $array['changelog']);
        $this->assertFalse($array['is_major_update']);
        $this->assertTrue($array['core_compatible']);
        $this->assertEquals('^1.0', $array['core_requirement']);
        $this->assertEquals('https://example.com/download.zip', $array['download_url']);
        $this->assertEquals(512000, $array['download_size']);
    }

    public function test_to_array_handles_null_values(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'minimal',
            fromVersion: '1.0.0',
            toVersion: '1.0.1',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $array = $dto->toArray();

        $this->assertNull($array['core_requirement']);
        $this->assertNull($array['download_url']);
        $this->assertNull($array['download_size']);
    }

    public function test_dto_is_readonly(): void
    {
        $dto = new UpdatePreviewDTO(
            moduleName: 'test',
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }
}
