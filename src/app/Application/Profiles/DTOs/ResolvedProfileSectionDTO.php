<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ProfileRegion;
use stdClass;

/**
 * A profile section ready for the browser: `ProfileSection.vue` draws `label` as its title, loads
 * `component` from the module (or from the core when `module` is null) and binds `props` to it.
 */
final readonly class ResolvedProfileSectionDTO
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public string $key,
        public ?string $module,
        public string $component,
        public ProfileRegion $region,
        public string $label,
        public array $props,
    ) {}

    /**
     * Empty props go out as `{}`, never `[]`, so the browser always gets an object.
     *
     * @return array{key: string, module: string|null, component: string, region: string, label: string, props: array<string, mixed>|stdClass}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'module' => $this->module,
            'component' => $this->component,
            'region' => $this->region->value,
            'label' => $this->label,
            'props' => $this->props === [] ? new stdClass : $this->props,
        ];
    }
}
