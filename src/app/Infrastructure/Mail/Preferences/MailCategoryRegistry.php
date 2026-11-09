<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Domain\Mail\Exceptions\InvalidMailCategoryException;
use App\Domain\Mail\ValueObjects\MailCategoryKey;
use Illuminate\Support\Facades\Log;

final class MailCategoryRegistry implements MailCategoryRegistryInterface
{
    private const string CORE_PREFIX = 'core';

    /**
     * @var array<string, MailCategoryDTO>
     */
    private array $categories = [];

    /**
     * @var array<class-string, string>
     */
    private array $notificationCategories = [];

    /**
     * @param  bool  $rejectsInvalidCategories  Throw on an invalid category instead of skipping it with a warning
     */
    public function __construct(
        private readonly bool $rejectsInvalidCategories = false,
    ) {}

    public function register(MailCategoryDTO $category, ?string $module = null): void
    {
        try {
            $this->validate($category, $module ?? self::CORE_PREFIX);
        } catch (InvalidMailCategoryException $exception) {
            if ($this->rejectsInvalidCategories) {
                throw $exception;
            }

            Log::warning($exception->getMessage(), [
                'module' => $module ?? self::CORE_PREFIX,
                'key' => $category->key,
            ]);

            return;
        }

        $this->categories[$category->key] = $category;
    }

    public function registerMany(array $categories, ?string $module = null): void
    {
        foreach ($categories as $category) {
            $this->register($category, $module);
        }
    }

    public function all(): array
    {
        return array_values($this->categories);
    }

    public function find(string $key): ?MailCategoryDTO
    {
        return $this->categories[$key] ?? null;
    }

    public function assignNotification(string $notificationClass, string $categoryKey): void
    {
        $this->notificationCategories[$notificationClass] = $categoryKey;
    }

    public function forNotification(object $notification): ?MailCategoryDTO
    {
        if ($notification instanceof HasMailCategory) {
            return $this->find($notification->mailCategory());
        }

        foreach ([$notification::class, ...array_values(class_parents($notification))] as $class) {
            if (isset($this->notificationCategories[$class])) {
                return $this->find($this->notificationCategories[$class]);
            }
        }

        return null;
    }

    /**
     * @throws InvalidMailCategoryException
     */
    private function validate(MailCategoryDTO $category, string $prefix): void
    {
        if (MailCategoryKey::fromString($category->key)->prefix() !== $prefix) {
            throw InvalidMailCategoryException::wrongPrefix($category->key, $prefix);
        }

        if ($category->isMandatory() && trim((string) $category->mandatoryReason) === '') {
            throw InvalidMailCategoryException::mandatoryWithoutReason($category->key);
        }
    }
}
