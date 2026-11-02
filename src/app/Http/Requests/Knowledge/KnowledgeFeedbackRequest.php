<?php

declare(strict_types=1);

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

final class KnowledgeFeedbackRequest extends FormRequest
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
            'helpful' => ['required', 'boolean'],
        ];
    }

    public function isHelpful(): bool
    {
        return $this->boolean('helpful');
    }
}
