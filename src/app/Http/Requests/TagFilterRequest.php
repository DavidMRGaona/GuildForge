<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared request for filtering by tags.
 *
 * Parses comma-separated tag slugs from the query string.
 * Used by EventController, ArticleController, and GalleryController.
 */
final class TagFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tags' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get parsed tag slugs from the query string.
     *
     * @return array<int, string>|null
     */
    public function getTagSlugs(): ?array
    {
        $tags = $this->query('tags');

        if (! is_string($tags) || $tags === '') {
            return null;
        }

        $slugs = array_filter(explode(',', $tags));

        return $slugs !== [] ? array_values($slugs) : null;
    }

    /**
     * Get the current page number from the query string.
     */
    public function getPage(): int
    {
        return max(1, (int) $this->query('page', 1));
    }
}
