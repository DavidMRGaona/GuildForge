<?php

declare(strict_types=1);

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

final class KnowledgeSearchRequest extends FormRequest
{
    private const int MIN_QUERY_LENGTH = 2;

    private const int MAX_QUERY_LENGTH = 100;

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
            'q' => ['nullable', 'string', 'max:'.self::MAX_QUERY_LENGTH],
        ];
    }

    public function searchQuery(): string
    {
        return trim((string) $this->query('q', ''));
    }

    public function isSearchable(): bool
    {
        return mb_strlen($this->searchQuery()) >= self::MIN_QUERY_LENGTH;
    }
}
