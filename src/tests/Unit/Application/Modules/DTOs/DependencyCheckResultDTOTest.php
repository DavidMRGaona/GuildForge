<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Modules\DTOs;

use App\Application\Modules\DTOs\DependencyCheckResultDTO;
use PHPUnit\Framework\TestCase;

final class DependencyCheckResultDTOTest extends TestCase
{
    public function test_it_creates_dependency_check_result_dto_from_constructor(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: true,
            missing: [],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $this->assertTrue($dto->satisfied);
        $this->assertEquals([], $dto->missing);
        $this->assertEquals([], $dto->versionMismatch);
        $this->assertEquals([], $dto->circularDependencies);
        $this->assertEquals([], $dto->unsatisfiedRequirements);
    }

    public function test_satisfied_property_is_true_when_no_issues(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: true,
            missing: [],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $this->assertTrue($dto->satisfied);
        $this->assertFalse($dto->hasErrors());
    }

    public function test_satisfied_property_is_false_when_missing_dependencies(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: ['payment-gateway', 'email-service'],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $this->assertFalse($dto->satisfied);
        $this->assertEquals(['payment-gateway', 'email-service'], $dto->missing);
    }

    public function test_satisfied_property_is_false_when_version_mismatches(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: [],
            versionMismatch: [
                'core-auth' => [
                    'required' => '^2.0',
                    'current' => '1.5.0',
                ],
                'notifications' => [
                    'required' => '^3.1',
                    'current' => '3.0.5',
                ],
            ],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $this->assertFalse($dto->satisfied);
        $this->assertArrayHasKey('core-auth', $dto->versionMismatch);
        $this->assertEquals('^2.0', $dto->versionMismatch['core-auth']['required']);
        $this->assertEquals('1.5.0', $dto->versionMismatch['core-auth']['current']);
    }

    public function test_satisfied_property_is_false_when_circular_dependencies_exist(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: [],
            versionMismatch: [],
            circularDependencies: [
                ['module-a', 'module-b', 'module-c', 'module-a'],
                ['forum', 'notifications', 'forum'],
            ],
            unsatisfiedRequirements: [],
        );

        $this->assertFalse($dto->satisfied);
        $this->assertCount(2, $dto->circularDependencies);
        $this->assertEquals(['module-a', 'module-b', 'module-c', 'module-a'], $dto->circularDependencies[0]);
    }

    public function test_has_errors_returns_false_when_satisfied(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: true,
            missing: [],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $this->assertFalse($dto->hasErrors());
    }

    public function test_has_errors_returns_true_when_any_errors_exist(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: ['missing-module'],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $this->assertTrue($dto->hasErrors());
    }

    public function test_get_error_messages_returns_empty_array_when_no_errors(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: true,
            missing: [],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $this->assertEquals([], $dto->getErrorMessages());
    }

    public function test_get_error_messages_returns_list_of_human_readable_error_messages(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: ['payment-gateway', 'email-service'],
            versionMismatch: [
                'core-auth' => [
                    'required' => '^2.0',
                    'current' => '1.5.0',
                ],
            ],
            circularDependencies: [
                ['module-a', 'module-b', 'module-a'],
            ],
            unsatisfiedRequirements: [
                'php' => 'Requires PHP ^8.2, but 8.1.0 is installed',
                'ext-redis' => 'Requires ext-redis extension',
            ],
        );

        $messages = $dto->getErrorMessages();

        $this->assertIsArray($messages);
        $this->assertNotEmpty($messages);

        // Check that error messages contain relevant information
        $allMessages = implode(' ', $messages);
        $this->assertStringContainsString('payment-gateway', $allMessages);
        $this->assertStringContainsString('email-service', $allMessages);
        $this->assertStringContainsString('core-auth', $allMessages);
        $this->assertStringContainsString('module-a', $allMessages);
        $this->assertStringContainsString('PHP ^8.2', $allMessages);
    }

    public function test_get_error_messages_formats_missing_dependencies(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: ['forum', 'shop'],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $messages = $dto->getErrorMessages();

        $this->assertCount(2, $messages);
        $this->assertStringContainsString('forum', $messages[0]);
        $this->assertStringContainsString('missing', strtolower($messages[0]));
    }

    public function test_get_error_messages_formats_version_mismatches(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: [],
            versionMismatch: [
                'notifications' => [
                    'required' => '^3.0',
                    'current' => '2.5.1',
                ],
            ],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $messages = $dto->getErrorMessages();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('notifications', $messages[0]);
        $this->assertStringContainsString('^3.0', $messages[0]);
        $this->assertStringContainsString('2.5.1', $messages[0]);
    }

    public function test_get_error_messages_formats_circular_dependencies(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: [],
            versionMismatch: [],
            circularDependencies: [
                ['forum', 'notifications', 'forum'],
            ],
            unsatisfiedRequirements: [],
        );

        $messages = $dto->getErrorMessages();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('circular', strtolower($messages[0]));
        $this->assertStringContainsString('forum', $messages[0]);
        $this->assertStringContainsString('notifications', $messages[0]);
    }

    public function test_get_error_messages_includes_unsatisfied_requirements(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: [],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: [
                'php' => 'Requires PHP ^8.2, but 8.1.0 is installed',
                'ext-gd' => 'Requires ext-gd extension',
            ],
        );

        $messages = $dto->getErrorMessages();

        $this->assertCount(2, $messages);
        $this->assertStringContainsString('PHP ^8.2', $messages[0]);
        $this->assertStringContainsString('ext-gd', $messages[1]);
    }

    public function test_has_errors_returns_true_for_version_mismatches(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: [],
            versionMismatch: ['core' => ['required' => '^2.0', 'current' => '1.0.0']],
            circularDependencies: [],
            unsatisfiedRequirements: [],
        );

        $this->assertTrue($dto->hasErrors());
    }

    public function test_has_errors_returns_true_for_circular_dependencies(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: [],
            versionMismatch: [],
            circularDependencies: [['a', 'b', 'a']],
            unsatisfiedRequirements: [],
        );

        $this->assertTrue($dto->hasErrors());
    }

    public function test_has_errors_returns_true_for_unsatisfied_requirements(): void
    {
        $dto = new DependencyCheckResultDTO(
            satisfied: false,
            missing: [],
            versionMismatch: [],
            circularDependencies: [],
            unsatisfiedRequirements: ['php' => 'PHP version mismatch'],
        );

        $this->assertTrue($dto->hasErrors());
    }
}
