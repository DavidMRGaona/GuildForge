<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Mail\Enums\MailPauseDuration;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * How long the signed-in person pauses their optional mail.
 */
final class PauseMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a person's own, live account has mail to pause
        $user = $this->user();

        return $user instanceof UserModel && $user->anonymized_at === null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'duration' => ['required', 'string', Rule::enum(MailPauseDuration::class)],
        ];
    }

    public function duration(): MailPauseDuration
    {
        return MailPauseDuration::from((string) $this->validated('duration'));
    }
}
