<?php

declare(strict_types=1);

namespace App\Http\Rules;

use App\Domain\Profiles\PublicName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A public name that holds something besides blanks (core#855). TrimStrings only trims ASCII
 * blanks, so a value of no-break or wide spaces would pass `required` and leave the account
 * shown by nothing; this refuses it with the same message as an empty field.
 */
final class PublicNameIsFilled implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && PublicName::isMissing($value)) {
            $fail('validation.required')->translate();
        }
    }
}
