<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchRequest extends FormRequest
{
    private const int MIN_QUERY_LENGTH = 2;

    private const int MAX_QUERY_LENGTH = 100;

    /** The search engine pages up to 1000 hits: 50 pages of 20. */
    public const int MAX_PAGE = 50;

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
     * The tab asked for (`tipo`), or null; whether it exists is for the caller to decide.
     */
    public function tab(): ?string
    {
        $tab = $this->query('tipo');

        return is_string($tab) && $tab !== '' ? $tab : null;
    }

    /**
     * The page asked for (`pagina`), from 1 to MAX_PAGE; anything else reads as the first page.
     */
    public function page(): int
    {
        $page = filter_var($this->query('pagina'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_PAGE]]);

        return is_int($page) ? $page : 1;
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
