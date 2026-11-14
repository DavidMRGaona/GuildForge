<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Registration\DTOs\RegistrationFieldDTO;
use App\Application\Registration\Services\RegistrationFieldRegistryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * A new account. Below it, the optional fields of the enabled modules (core#699), sent as
 * `registration_fields[<key>]`: only the fields available now are read, each checked as a boolean
 * and with its module's rules. Their errors are keyed `registration_fields.<key>` (keys hold dots,
 * so they are never read with dot notation). Anything else in `registration_fields` is dropped,
 * and a field whose rules throw is dropped too: a module never stops a registration.
 */
final class RegisterRequest extends FormRequest
{
    private const string FIELDS = 'registration_fields';

    /** @var ?list<RegistrationFieldDTO> the module fields available for this request, read once */
    private ?array $availableFields = null;

    /** @var array<string, bool> module field values that passed their checks, keyed by field key */
    private array $fieldValues = [];

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            self::FIELDS => ['nullable', 'array', 'max:50'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has(self::FIELDS)) {
                    return;
                }

                $this->checkModuleFields($validator);
            },
        ];
    }

    /**
     * The values of the module fields that were available and passed their checks, false when
     * left unticked. Empty when no module field was available.
     *
     * @return array<string, bool>
     */
    public function registrationFieldValues(): array
    {
        return $this->fieldValues;
    }

    private function checkModuleFields(Validator $validator): void
    {
        $submitted = $this->input(self::FIELDS);
        $submitted = is_array($submitted) ? $submitted : [];

        foreach ($this->availableFields() as $field) {
            $value = $submitted[$field->key] ?? null;

            try {
                $check = ValidatorFactory::make(
                    ['value' => $value],
                    ['value' => ['nullable', 'boolean', ...$field->rules]],
                    [],
                    ['value' => $field->label],
                );
                $failed = $check->fails();
            } catch (Throwable $exception) {
                Log::warning('[RegisterRequest] Registration field could not be checked, value dropped', [
                    'key' => $field->key,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($failed) {
                $validator->errors()->add(self::FIELDS.'.'.$field->key, (string) $check->errors()->first('value'));

                continue;
            }

            $this->fieldValues[$field->key] = in_array($value, [true, 1, '1'], true);
        }
    }

    /**
     * @return list<RegistrationFieldDTO>
     */
    private function availableFields(): array
    {
        return $this->availableFields ??= app(RegistrationFieldRegistryInterface::class)->available();
    }
}
