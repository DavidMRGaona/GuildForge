<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class PagePrefixDTO
{
    /**
     * @param  bool  $usesCoreLayout  The prefix's pages declare no layout of their own: the core
     *                                renders its DefaultLayout around them, kept across visits.
     *                                False for modules whose pages still wrap <DefaultLayout>.
     */
    public function __construct(
        public string $prefix,
        public string $module,
        public bool $usesCoreLayout = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            prefix: $data['prefix'] ?? '',
            module: $data['module'] ?? '',
            usesCoreLayout: (bool) ($data['usesCoreLayout'] ?? false),
        );
    }

    /**
     * @return array{prefix: string, module: string, usesCoreLayout: bool}
     */
    public function toArray(): array
    {
        return [
            'prefix' => $this->prefix,
            'module' => $this->module,
            'usesCoreLayout' => $this->usesCoreLayout,
        ];
    }
}
