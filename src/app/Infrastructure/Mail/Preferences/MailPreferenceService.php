<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Mail\Contracts\MailDeliveryRuleInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailPreferenceDTO;
use App\Application\Mail\DTOs\MailPreferenceSectionDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Domain\Mail\Enums\MailDelivery;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationPreferenceRepositoryInterface;
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
        private SiteNotificationPreferenceRepositoryInterface $sitePreferences,
    ) {}

    public function sectionsFor(object $user): array
    {
        if (! $user instanceof UserModel) {
            return [];
        }

        $userId = UserId::fromString($user->id);
        $disabled = $this->preferences->disabledFor($userId);
        $digest = $this->preferences->digestFor($userId);
        $siteDisabled = $this->sitePreferences->disabledFor($userId);

        /** @var array<string, list<MailPreferenceDTO>> $sections */
        $sections = [];
        /** @var array<string, string> $icons */
        $icons = [];
        foreach ($this->categories->all() as $category) {
            if (! $this->isVisibleTo($category, $user)) {
                continue;
            }

            if ($category->icon !== null && ! isset($icons[$category->section])) {
                $icons[$category->section] = $category->icon;
            }

            $delivery = $this->deliveryOf($category, $disabled, $digest);

            $sections[$category->section][] = new MailPreferenceDTO(
                key: $category->key,
                label: $this->translate($category->label),
                description: $this->translate($category->description),
                mandatory: $category->isMandatory(),
                mandatoryReason: $category->mandatoryReason === null ? null : $this->translate($category->mandatoryReason),
                enabled: $delivery !== MailDelivery::None,
                digestible: $this->offersDigest($category),
                delivery: $delivery->value,
                mail: $category->mail,
                site: $category->site,
                siteEnabled: $category->site && ($category->isMandatory() || ! in_array($category->key, $siteDisabled, true)),
            );
        }

        $result = [];
        foreach ($sections as $section => $preferences) {
            $result[] = new MailPreferenceSectionDTO(key: $section, label: $this->translate($section), categories: $preferences, icon: $icons[$section] ?? null);
        }

        return $result;
    }

    public function update(object $user, array $changes): void
    {
        if (! $user instanceof UserModel) {
            throw new InvalidArgumentException('Only accounts have mail preferences.');
        }

        foreach ($changes as $key => $choice) {
            $key = (string) $key;
            $category = $this->categories->find($key);

            if (! $category instanceof MailCategoryDTO) {
                throw MailPreferenceNotEditableException::unknown($key);
            }

            if (! $category->mail) {
                throw MailPreferenceNotEditableException::notByMail($key);
            }

            if ($category->isMandatory()) {
                throw MailPreferenceNotEditableException::mandatory($key);
            }

            if (! $this->isVisibleTo($category, $user)) {
                throw MailPreferenceNotEditableException::notVisible($key);
            }

            if ($choice === MailDelivery::Digest && ! $this->offersDigest($category)) {
                throw MailPreferenceNotEditableException::notDigestible($key);
            }
        }

        if ($changes !== []) {
            $this->preferences->save(UserId::fromString($user->id), $changes);
        }
    }

    public function receives(object $user, MailCategoryDTO $category, ?MailSubjectDTO $subject = null): bool
    {
        // A category shown only on the site is never mailed (core#491)
        if (! $category->mail) {
            return false;
        }

        if ($category->isMandatory() || ! $user instanceof UserModel) {
            return true;
        }

        foreach ($this->rules as $rule) {
            try {
                if (! $rule->allows($user, $category, $subject)) {
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

    /**
     * A digest choice stored for a category that is no longer digestible counts as «at once»,
     * as the delivery itself does.
     *
     * @param  list<string>  $disabled
     * @param  list<string>  $digest
     */
    private function deliveryOf(MailCategoryDTO $category, array $disabled, array $digest): MailDelivery
    {
        if ($category->isMandatory()) {
            return MailDelivery::Immediate;
        }

        if (in_array($category->key, $disabled, true)) {
            return MailDelivery::None;
        }

        return $this->offersDigest($category) && in_array($category->key, $digest, true)
            ? MailDelivery::Digest
            : MailDelivery::Immediate;
    }

    private function offersDigest(MailCategoryDTO $category): bool
    {
        return $category->digestible && ! $category->isMandatory();
    }

    private function isVisibleTo(MailCategoryDTO $category, UserModel $user): bool
    {
        return MailCategoryAudience::includes($category, $user);
    }

    private function translate(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
