<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Knowledge\Services\KnowledgeLocaleResolverInterface;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;

final readonly class KnowledgeLocaleResolver implements KnowledgeLocaleResolverInterface
{
    public function __construct(
        private GuideRepositoryInterface $guides,
    ) {}

    public function locale(): string
    {
        $locale = app()->getLocale();

        if ($this->guides->hasLocale($locale)) {
            return $locale;
        }

        $fallback = config('knowledge.fallback_locale');

        return is_string($fallback) && $fallback !== '' ? $fallback : 'es';
    }
}
