<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\UserRole;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    public function test_it_has_admin_role(): void
    {
        $this->assertContains(
            UserRole::Admin,
            UserRole::cases()
        );
    }

    public function test_it_has_editor_role(): void
    {
        $this->assertContains(
            UserRole::Editor,
            UserRole::cases()
        );
    }

    public function test_it_has_member_role(): void
    {
        $this->assertContains(
            UserRole::Member,
            UserRole::cases()
        );
    }

    public function test_admin_has_correct_value(): void
    {
        $this->assertEquals('admin', UserRole::Admin->value);
    }

    public function test_editor_has_correct_value(): void
    {
        $this->assertEquals('editor', UserRole::Editor->value);
    }

    public function test_member_has_correct_value(): void
    {
        $this->assertEquals('member', UserRole::Member->value);
    }

    public function test_it_has_exactly_three_cases(): void
    {
        $this->assertCount(3, UserRole::cases());
    }

    public function test_admin_label_returns_administrator(): void
    {
        $this->assertEquals('Administrator', UserRole::Admin->label());
    }

    public function test_editor_label_returns_editor(): void
    {
        $this->assertEquals('Editor', UserRole::Editor->label());
    }

    public function test_member_label_returns_member(): void
    {
        $this->assertEquals('Member', UserRole::Member->label());
    }
}
