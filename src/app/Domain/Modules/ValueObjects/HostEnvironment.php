<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use App\Domain\Modules\Enums\RequirementType;

final readonly class HostEnvironment
{
    /**
     * @param  list<string>  $extensions  Loaded PHP extensions, lowercase
     */
    public function __construct(
        public ModuleVersion $core,
        public ModuleVersion $php,
        public ModuleVersion $laravel,
        public ?ModuleVersion $filament,
        public array $extensions,
    ) {
    }

    public function versionOf(RequirementType $type): ?ModuleVersion
    {
        return match ($type) {
            RequirementType::Core => $this->core,
            RequirementType::Php => $this->php,
            RequirementType::Laravel => $this->laravel,
            RequirementType::Filament => $this->filament,
            RequirementType::Extension, RequirementType::Manifest => null,
        };
    }

    /**
     * Case-insensitive; accepts Composer's "ext-" prefix.
     */
    public function hasExtension(string $name): bool
    {
        $normalized = strtolower($name);

        if (str_starts_with($normalized, 'ext-')) {
            $normalized = substr($normalized, 4);
        }

        return in_array($normalized, $this->extensions, true);
    }
}
