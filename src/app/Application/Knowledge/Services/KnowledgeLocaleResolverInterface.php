<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

interface KnowledgeLocaleResolverInterface
{
    /**
     * The application locale when guides exist for it, the configured fallback otherwise.
     */
    public function locale(): string;
}
