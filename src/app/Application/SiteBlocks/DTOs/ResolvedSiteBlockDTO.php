<?php

declare(strict_types=1);

namespace App\Application\SiteBlocks\DTOs;

use stdClass;

/**
 * A site block ready for the browser: `SiteBlock.vue` loads `component` from the module (or from
 * the core when `module` is null) and binds `props` to it.
 */
final readonly class ResolvedSiteBlockDTO
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public string $key,
        public ?string $module,
        public string $component,
        public array $props,
    ) {}

    /**
     * Empty props go out as `{}`, never `[]`, so the browser always gets an object.
     *
     * @return array{key: string, module: string|null, component: string, props: array<string, mixed>|stdClass}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'module' => $this->module,
            'component' => $this->component,
            'props' => $this->props === [] ? new stdClass : $this->props,
        ];
    }
}
