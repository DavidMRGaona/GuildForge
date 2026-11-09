<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Mail\Contracts\MailDeliveryRuleInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailPreferenceDTO;
use App\Application\Mail\DTOs\MailPreferenceSectionDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final readonly class MailPreferenceService implements MailPreferenceServiceInterface
{
    /**
     * @param  iterable<MailDeliveryRuleInterface>  $rules  Resolved when a mail is checked, so a rule tagged later counts too
     */
    public function __construct(
        private MailCategoryRegistryInterface $categories,
        private MailPreferenceRepositoryInterface $preferences,
        private iterable $rules,
    ) {}

    public function sectionsFor(object $user): array
    {
        if (! $user instanceof UserModel) {
            return [];
        }

        $disabled = $this->preferences->disabledFor(UserId::fromString($user->id));

        /** @var array<string, list<MailPreferenceDTO>> $sections */
        $sections = [];
        foreach ($this->categories->all() as $category) {
            if (! $this->isVisibleTo($category, $user)) {
                continue;
            }

            $sections[$category->section][] = new MailPreferenceDTO(
                key: $category->key,
                label: $this->translate($category->label),
                description: $this->translate($category->description),
                mandatory: $category->isMandatory(),
                mandatoryReason: $category->mandatoryReason === null ? null : $this->translate($category->mandatoryReason),
                enabled: $category->isMandatory() || ! in_array($category->key, $disabled, true),
            );
        }

        $result = [];
        foreach ($sections as $section => $preferences) {
            $result[] = new MailPreferenceSectionDTO(key: $section, label: $this->translate($section), categories: $preferences);
        }

        return $result;
    }

    public function update(object $user, array $changes): void
    {
        if (! $user instanceof UserModel) {
            throw new InvalidArgumentException('Only accounts have mail preferences.');
        }

        foreach (array_keys($changes) as $key) {
            $key = (string) $key;
            $category = $this->categories->find($key);

            if (! $category instanceof MailCategoryDTO) {
                throw MailPreferenceNotEditableException::unknown($key);
            }

            if ($category->isMandatory()) {
                throw MailPreferenceNotEditableException::mandatory($key);
            }

            if (! $this->isVisibleTo($category, $user)) {
                throw MailPreferenceNotEditableException::notVisible($key);
            }
        }

        if ($changes !== []) {
            $this->preferences->save(UserId::fromString($user->id), $changes);
        }
    }

    public function receives(object $user, MailCategoryDTO $category): bool
    {
        if ($category->isMandatory() || ! $user instanceof UserModel) {
            return true;
        }

        foreach ($this->rules as $rule) {
            try {
                if (! $rule->allows($user, $category)) {
                    return false;
                }
            } catch (Throwable $exception) {
                // Missing a cancellation is worse than one reminder too many
                Log::warning('A mail delivery rule failed; the mail is sent.', [
                    'rule' => $rule::class,
                    'category' => $category->key,
                    'user_id' => $user->id,
                    'exception' => $exception,
                ]);
            }
        }

        return true;
    }

    private function isVisibleTo(MailCategoryDTO $category, UserModel $user): bool
    {
        if (! $category->audience instanceof \Closure) {
            return true;
        }

        try {
            return ($category->audience)($user) === true;
        } catch (Throwable $exception) {
            // Hidden from the list only: whether the mail goes out never depends on it
            Log::warning('The audience check of a mail category failed; it is not shown.', [
                'category' => $category->key,
                'user_id' => $user->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function translate(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
