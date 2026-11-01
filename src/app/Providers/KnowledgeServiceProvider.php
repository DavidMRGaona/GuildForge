<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Repositories\EloquentGuideRepository;
use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GuideRepositoryInterface::class, EloquentGuideRepository::class);
    }
}
