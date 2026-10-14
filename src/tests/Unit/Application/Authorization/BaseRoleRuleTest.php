<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Authorization;

use App\Application\Authorization\Services\BaseRoleRule;
use App\Domain\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BaseRoleRuleTest extends TestCase
{
    public function test_the_base_role_is_member(): void
    {
        $this->assertSame(UserRole::Member->value, BaseRoleRule::NAME);
        $this->assertTrue((new BaseRoleRule)->isBaseRoleName('member'));
    }

    #[DataProvider('otherNames')]
    public function test_no_other_name_is_the_base_role(string $name): void
    {
        $this->assertFalse((new BaseRoleRule)->isBaseRoleName($name));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherNames(): array
    {
        return [
            'admin' => ['admin'],
            'editor' => ['editor'],
            'socio' => ['socio'],
            'different case' => ['Member'],
            'padded' => [' member'],
            'empty' => [''],
        ];
    }
}
