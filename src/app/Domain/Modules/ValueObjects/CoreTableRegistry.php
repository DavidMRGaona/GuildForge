<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

final readonly class CoreTableRegistry
{
    /** @var array<int, string> */
    private const array CORE_TABLES = [
        // Framework
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        // Content
        'events',
        'galleries',
        'photos',
        'articles',
        'settings',
        'hero_slides',
        'tags',
        'event_tag',
        'article_tag',
        'gallery_tag',
        // Infrastructure
        'modules',
        'permissions',
        'roles',
        'role_permission',
        'user_role',
        'menu_items',
        'slug_redirects',
        'module_update_history',
        'module_update_logs',
        'module_seeder_history',
        'core_update_history',
        'core_seeder_history',
        'email_logs',
        'ses_usage_records',
    ];

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return self::CORE_TABLES;
    }

    public function isCore(string $table): bool
    {
        return in_array($table, self::CORE_TABLES, true);
    }
}
