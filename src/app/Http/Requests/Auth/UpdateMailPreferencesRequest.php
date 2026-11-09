<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape of the mail choices only: which categories the person may change is decided by
 * MailPreferenceServiceInterface::update(), never by the client.
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1', 'max:200'],
            'preferences.*' => ['boolean:strict'],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function preferences(): array
    {
        /** @var array<string, bool> $preferences */
        $preferences = $this->validated('preferences');

        return $preferences;
    }
}
