<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use League\CommonMark\Node\Inline\AbstractInline;

final class ActionButton extends AbstractInline
{
    public function __construct(
        public readonly string $route,
        public readonly string $label,
        public readonly ?string $condition,
    ) {
        parent::__construct();
    }
}
