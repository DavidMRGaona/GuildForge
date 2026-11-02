<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The avatar may come alone (the profile header), so a pending email change is not touched
            'name' => ['required_without:avatar', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required_with:name',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
