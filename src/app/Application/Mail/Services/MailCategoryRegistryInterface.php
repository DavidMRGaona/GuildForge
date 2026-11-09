<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Domain\Mail\Exceptions\InvalidMailCategoryException;

/**
 * Mail categories of the core and of the enabled modules. Modules declare theirs from
 * ModuleServiceProvider::registerMailCategories().
 */
interface MailCategoryRegistryInterface
{
    /**
     * Register a category; a repeated key replaces the previous one. The key must start with
     * "<module>." (or "core." without a module) and a mandatory category needs its reason.
     *
     * @throws InvalidMailCategoryException in local and testing; elsewhere the category is skipped with a warning
     */
    public function register(MailCategoryDTO $category, ?string $module = null): void;

    /**
     * @param  list<MailCategoryDTO>  $categories
     *
     * @throws InvalidMailCategoryException in local and testing
     */
    public function registerMany(array $categories, ?string $module = null): void;

    /**
     * @return list<MailCategoryDTO> In registration order: the core first, then the modules.
     */
    public function all(): array;

    public function find(string $key): ?MailCategoryDTO;

    /**
     * Put a notification class that cannot implement HasMailCategory (the framework's own,
     * such as the password reset) in a category. Its subclasses follow it.
     *
     * @param  class-string  $notificationClass
     */
    public function assignNotification(string $notificationClass, string $categoryKey): void;

    /**
     * The registered category a notification belongs to, or null when it declares none or an
     * unregistered one.
     */
    public function forNotification(object $notification): ?MailCategoryDTO;
}
