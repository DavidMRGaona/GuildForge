<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The header panel's request: no validation rules, so a bad query gets an empty JSON answer
 * instead of a redirect or a 422 the panel would have to handle.
 */
final class SearchSuggestionsRequest extends FormRequest
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
        return [];
    }

    public function searchQuery(): string
    {
        $query = $this->query('q');

        return is_string($query) ? trim($query) : '';
    }

    public function isSearchable(): bool
    {
        $length = mb_strlen($this->searchQuery());

        return $length >= SearchQueryLength::MIN && $length <= SearchQueryLength::MAX;
    }
}
