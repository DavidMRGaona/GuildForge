<?php

declare(strict_types=1);

namespace App\Application\Impersonation\Services;

use App\Application\Impersonation\DTOs\ViewedRoleDTO;

/**
 * The roles an administrator may view the site as.
 */
interface ImpersonationRolesInterface
{
    /**
     * @param  list<string>  $ids
     * @return list<ViewedRoleDTO> only those that exist, in the order of the role list (display name, then name)
     */
    public function find(array $ids): array;

    /**
     * The base role (member), which every view includes; null when it does not exist.
     */
    public function baseRoleId(): ?string;

    /**
     * @return list<ViewedRoleDTO> every role but admin, in the order of the role list
     */
    public function offered(): array;
}
