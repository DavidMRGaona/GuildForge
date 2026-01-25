<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class CalendarRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
        ];
    }

    /**
     * Get the start date from the request.
     */
    public function startDate(): string
    {
        return $this->validated('start');
    }

    /**
     * Get the end date from the request.
     */
    public function endDate(): string
    {
        return $this->validated('end');
    }
}
