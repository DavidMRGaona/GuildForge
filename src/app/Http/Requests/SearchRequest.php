<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchRequest extends FormRequest
{
    private const MIN_QUERY_LENGTH = 2;

    private const MAX_QUERY_LENGTH = 100;

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
            'q' => ['nullable', 'string', 'max:'.self::MAX_QUERY_LENGTH],
        ];
    }

    /**
     * Get the search query, trimmed.
     */
    public function searchQuery(): string
    {
        return trim((string) $this->query('q', ''));
    }

    /**
     * Check if the search query is valid (meets minimum length).
     */
    public function hasValidSearchQuery(): bool
    {
        return mb_strlen($this->searchQuery()) >= self::MIN_QUERY_LENGTH;
    }

    /**
     * Check if the search query is too short.
     */
    public function isQueryTooShort(): bool
    {
        $query = $this->searchQuery();

        return $query !== '' && mb_strlen($query) < self::MIN_QUERY_LENGTH;
    }
}
