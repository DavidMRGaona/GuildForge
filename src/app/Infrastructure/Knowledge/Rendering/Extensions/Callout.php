<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use League\CommonMark\Node\Block\AbstractBlock;

final class Callout extends AbstractBlock
{
    public function __construct(public readonly string $type)
    {
        parent::__construct();
    }
}
