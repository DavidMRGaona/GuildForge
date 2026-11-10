<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Mail\Enums\MailDelivery;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape of the mail choices only: which categories the person may change, and which offer the
 * daily digest, is decided by MailPreferenceServiceInterface::update(), never by the client.
 * Each choice is the switch (a boolean) or one of the three deliveries (core#333). The site
 * switches of the notification centre (core#491) come in `site`, one boolean per category, and
 * are decided by SiteNotificationPreferenceServiceInterface::update().
 */
final class UpdateMailPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a person's own, live account has choices to save
        $user = $this->user();

        return $user instanceof UserModel && $user->anonymized_at === null;
    }

    /**
     * @return array<string, array<int, string|Closure>>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required_without:site', 'array', 'min:1', 'max:200'],
            'preferences.*' => [static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_bool($value) && (! is_string($value) || ! MailDelivery::tryFrom($value) instanceof MailDelivery)) {
                    $fail(__('validation.boolean', ['attribute' => $attribute]));
                }
            }],
            'site' => ['required_without:preferences', 'array', 'min:1', 'max:200'],
            'site.*' => [static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_bool($value)) {
                    $fail(__('validation.boolean', ['attribute' => $attribute]));
                }
            }],
        ];
    }

    /**
     * @return array<string, bool|MailDelivery>
     */
    public function preferences(): array
    {
        /** @var array<string, bool|string> $preferences */
        $preferences = $this->validated('preferences', []);

        return array_map(
            static fn (bool|string $choice): bool|MailDelivery => is_bool($choice) ? $choice : MailDelivery::from($choice),
            $preferences,
        );
    }

    /**
     * @return array<string, bool>
     */
    public function siteSwitches(): array
    {
        /** @var array<string, bool> */
        return $this->validated('site', []);
    }
}
