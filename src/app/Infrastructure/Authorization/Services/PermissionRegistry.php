<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\Exceptions\InvalidPermissionKeyException;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class PermissionRegistry implements PermissionRegistryInterface
{
    /**
     * @var array<string, PermissionDefinitionDTO>
     */
    private array $permissions = [];

    /**
     * @param  bool  $rejectsInvalidKeys  Throw on a key outside the "resource.action" form
     *                                    instead of skipping it with a warning
     */
    public function __construct(
        private readonly ?PermissionRepositoryInterface $permissionRepository = null,
        private readonly bool $rejectsInvalidKeys = false,
    ) {}

    /**
     * Only keys a PermissionKey accepts get in, so one malformed module permission can never
     * stop syncToDatabase() from saving the others.
     *
     * @throws InvalidPermissionKeyException when the key is invalid and invalid keys are rejected
     */
    public function register(PermissionDefinitionDTO $permission): void
    {
        try {
            new PermissionKey($permission->key);
        } catch (InvalidArgumentException) {
            $exception = InvalidPermissionKeyException::notResourceAction($permission->key, $permission->module);

            if ($this->rejectsInvalidKeys) {
                throw $exception;
            }

            Log::warning($exception->getMessage(), [
                'module' => $permission->module ?? 'core',
                'key' => $permission->key,
            ]);

            return;
        }

        $this->permissions[$permission->key] = $permission;
    }

    /**
     * @param  array<PermissionDefinitionDTO>  $permissions
     */
    public function registerMany(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->register($permission);
        }
    }

    /**
     * @return array<PermissionDefinitionDTO>
     */
    public function all(): array
    {
        return array_values($this->permissions);
    }

    public function find(string $key): ?PermissionDefinitionDTO
    {
        return $this->permissions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->permissions[$key]);
    }

    /**
     * @return array<PermissionDefinitionDTO>
     */
    public function forResource(string $resource): array
    {
        return array_values(
            array_filter(
                $this->permissions,
                fn (PermissionDefinitionDTO $permission): bool => $permission->resource === $resource
            )
        );
    }

    /**
     * @return array<PermissionDefinitionDTO>
     */
    public function forModule(string $module): array
    {
        return array_values(
            array_filter(
                $this->permissions,
                fn (PermissionDefinitionDTO $permission): bool => $permission->module === $module
            )
        );
    }

    /**
     * @return array<string, array<PermissionDefinitionDTO>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->permissions as $permission) {
            $grouped[$permission->resource][] = $permission;
        }

        ksort($grouped);

        return $grouped;
    }

    public function unregisterModule(string $module): void
    {
        $this->permissions = array_filter(
            $this->permissions,
            fn (PermissionDefinitionDTO $permission): bool => $permission->module !== $module
        );
    }

    public function syncToDatabase(): void
    {
        if (! $this->permissionRepository instanceof PermissionRepositoryInterface) {
            return;
        }

        $permissions = [];

        foreach ($this->permissions as $dto) {
            $permissions[] = new Permission(
                id: PermissionId::generate(),
                key: new PermissionKey($dto->key),
                label: $dto->label,
                resource: $dto->resource,
                action: $dto->action,
                module: $dto->module,
            );
        }

        $this->permissionRepository->saveMany($permissions);
    }

    public function clear(): void
    {
        $this->permissions = [];
    }
}
