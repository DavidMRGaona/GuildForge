<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Entities;

use App\Domain\Knowledge\Enums\GuideAudience;

final readonly class KnowledgeCategory
{
    public function __construct(
        public string $id,
        public ?string $module,
        public GuideAudience $audience,
        public int $order,
        public string $icon,
        public string $labelKey,
    ) {}
}
