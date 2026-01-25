<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Authorization\ValueObjects;

use App\Domain\Authorization\ValueObjects\PermissionKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PermissionKeyTest extends TestCase
{
    public function test_it_creates_with_valid_resource_action_format(): void
    {
        $key = new PermissionKey('events.create');

        $this->assertEquals('events.create', $key->value);
    }

    public function test_it_creates_with_another_valid_format(): void
    {
        $key = new PermissionKey('users.delete');

        $this->assertEquals('users.delete', $key->value);
    }

    public function test_it_creates_with_underscore_in_action(): void
    {
        $key = new PermissionKey('events.view_any');

        $this->assertEquals('events.view_any', $key->value);
        $this->assertEquals('events', $key->resource());
        $this->assertEquals('view_any', $key->action());
    }

    public function test_it_creates_with_underscore_in_resource(): void
    {
        $key = new PermissionKey('hero_slides.create');

        $this->assertEquals('hero_slides.create', $key->value);
        $this->assertEquals('hero_slides', $key->resource());
        $this->assertEquals('create', $key->action());
    }

    public function test_it_creates_with_underscores_in_both(): void
    {
        $key = new PermissionKey('hero_slides.view_any');

        $this->assertEquals('hero_slides.view_any', $key->value);
        $this->assertEquals('hero_slides', $key->resource());
        $this->assertEquals('view_any', $key->action());
    }

    public function test_it_creates_with_module_and_underscores(): void
    {
        $key = new PermissionKey('my_module:some_resource.view_any');

        $this->assertEquals('my_module:some_resource.view_any', $key->value);
        $this->assertEquals('my_module', $key->module());
        $this->assertEquals('some_resource', $key->resource());
        $this->assertEquals('view_any', $key->action());
    }

    public function test_it_creates_with_module_prefix(): void
    {
        $key = new PermissionKey('announcements:announcements.view');

        $this->assertEquals('announcements:announcements.view', $key->value);
    }

    public function test_it_creates_with_complex_module_permission(): void
    {
        $key = new PermissionKey('event-registrations:registrations.create');

        $this->assertEquals('event-registrations:registrations.create', $key->value);
    }

    public function test_it_throws_exception_for_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Permission key cannot be empty');

        new PermissionKey('');
    }

    public function test_it_throws_exception_for_missing_dot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Permission key must be in format "resource.action" or "module:resource.action"');

        new PermissionKey('eventsview');
    }

    public function test_it_throws_exception_for_multiple_dots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Permission key must be in format "resource.action" or "module:resource.action"');

        new PermissionKey('events.create.all');
    }

    public function test_it_throws_exception_for_missing_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Permission key must be in format "resource.action" or "module:resource.action"');

        new PermissionKey('.create');
    }

    public function test_it_throws_exception_for_missing_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Permission key must be in format "resource.action" or "module:resource.action"');

        new PermissionKey('events.');
    }

    public function test_resource_returns_resource_part(): void
    {
        $key = new PermissionKey('events.create');

        $this->assertEquals('events', $key->resource());
    }

    public function test_resource_returns_resource_part_with_module(): void
    {
        $key = new PermissionKey('announcements:announcements.view');

        $this->assertEquals('announcements', $key->resource());
    }

    public function test_action_returns_action_part(): void
    {
        $key = new PermissionKey('events.create');

        $this->assertEquals('create', $key->action());
    }

    public function test_action_returns_action_part_with_module(): void
    {
        $key = new PermissionKey('announcements:announcements.view');

        $this->assertEquals('view', $key->action());
    }

    public function test_module_returns_null_for_core_permissions(): void
    {
        $key = new PermissionKey('events.create');

        $this->assertNull($key->module());
    }

    public function test_module_returns_module_name_for_module_permissions(): void
    {
        $key = new PermissionKey('announcements:announcements.view');

        $this->assertEquals('announcements', $key->module());
    }

    public function test_module_returns_module_name_with_hyphens(): void
    {
        $key = new PermissionKey('event-registrations:registrations.create');

        $this->assertEquals('event-registrations', $key->module());
    }

    public function test_is_module_permission_returns_false_for_core(): void
    {
        $key = new PermissionKey('events.create');

        $this->assertFalse($key->isModulePermission());
    }

    public function test_is_module_permission_returns_true_for_module(): void
    {
        $key = new PermissionKey('announcements:announcements.view');

        $this->assertTrue($key->isModulePermission());
    }

    public function test_it_returns_value_as_string(): void
    {
        $key = new PermissionKey('events.create');

        $this->assertEquals('events.create', (string) $key);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $key1 = new PermissionKey('events.create');
        $key2 = new PermissionKey('events.create');

        $this->assertTrue($key1->equals($key2));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $key1 = new PermissionKey('events.create');
        $key2 = new PermissionKey('events.delete');

        $this->assertFalse($key1->equals($key2));
    }
}
