<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The thing the signed-in person wants to receive notices about again.
 */
final class UnmuteMailSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a person's own, live account has things muted
        $user = $this->user();

        return $user instanceof UserModel && $user->anonymized_at === null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:'.MailSubjectDTO::TYPE_MAX_LENGTH, 'regex:/^'.MailSubjectDTO::TYPE_PATTERN.'$/'],
            'id' => ['required', 'string', 'regex:/^'.MailSubjectDTO::ID_PATTERN.'$/'],
        ];
    }

    public function subjectType(): string
    {
        return (string) $this->validated('type');
    }

    public function subjectId(): string
    {
        return (string) $this->validated('id');
    }
}
