<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Changes to an account that already has two-factor authentication (new recovery codes, turning
 * it off; core#769): the current password and a code, from the app or a recovery code. The
 * password is checked first, so a wrong one never uses up a code.
 */
final class TwoFactorChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
