<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Profiles\PublicName;
use App\Http\Rules\PublicNameIsFilled;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The public name chosen on «Elige tu nombre público» (core#855): never empty.
 */
final class ChoosePublicNameRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:'.PublicName::MAX_LENGTH, new PublicNameIsFilled],
        ];
    }
}
