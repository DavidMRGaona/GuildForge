<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\Services\ModulePageRegistryInterface;

final class ModulePageRegistry implements ModulePageRegistryInterface
{
    /** @var array<PagePrefixDTO> */
    private array $prefixes = [];

    public function register(PagePrefixDTO $dto): void
    {
        $this->prefixes[] = $dto;
    }

    public function registerMany(array $prefixes): void
    {
        foreach ($prefixes as $prefix) {
            $this->register($prefix);
        }
    }

    public function all(): array
    {
        return $this->prefixes;
    }

    public function getModuleForPrefix(string $prefix): ?string
    {
        foreach ($this->prefixes as $dto) {
            if ($dto->prefix === $prefix) {
                return $dto->module;
            }
        }

        return null;
    }

    public function toInertiaPayload(): array
    {
        $payload = [];

        foreach ($this->prefixes as $dto) {
            $payload[$dto->prefix] = $dto->module;
        }

        return $payload;
    }

    public function unregisterModule(string $moduleName): void
    {
        $this->prefixes = array_values(
            array_filter(
                $this->prefixes,
                fn (PagePrefixDTO $dto): bool => $dto->module !== $moduleName
            )
        );
    }

    public function clear(): void
    {
        $this->prefixes = [];
    }
}
