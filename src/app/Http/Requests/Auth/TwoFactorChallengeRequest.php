<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'recovery' => ['sometimes', 'boolean'],
        ];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }

    public function usesRecoveryCode(): bool
    {
        return $this->boolean('recovery');
    }
}
