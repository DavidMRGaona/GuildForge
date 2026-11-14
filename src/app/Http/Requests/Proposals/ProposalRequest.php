<?php

declare(strict_types=1);

namespace App\Http\Requests\Proposals;

use App\Application\DTOs\Response\TagResponseDTO;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalTagOptionsInterface;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Forms\RichEditor\EditorImages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What every proposal form accepts. Only the fields in rules() reach the controller (validated()
 * drops the rest), so a forged request can never set a price, a slug, a status or an author.
 * Who may propose is checked in authorize() and again by the service.
 */
abstract class ProposalRequest extends FormRequest
{
    /** Longest text the service accepts for a note */
    private const int MAX_NOTE = 5000;

    /**
     * 403 before anything else for whoever may not propose this type (the service checks again).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(ProposalEligibilityServiceInterface::class)->check($this->type(), (string) $user->getAuthIdentifier())->allowed;
    }

    abstract protected function type(): ProposalType;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function commonRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Real content type, not the name: no SVG, same types and size as the editor's images
            'image' => ['nullable', 'file', 'mimetypes:'.implode(',', EditorImages::ACCEPTED_TYPES), 'max:'.EditorImages::MAX_SIZE],
            'remove_image' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['uuid'],
            'note' => ['nullable', 'string', 'max:'.self::MAX_NOTE],
            // Save as a draft (false) or send to review right after saving (true)
            'submit' => ['nullable', 'boolean'],
            ...$this->categoryRules(),
        ];
    }

    /**
     * Only whoever is offered the categories (ProposalTagOptionsInterface) sends one, required to
     * send it, since sending publishes it; for anyone else the field is not read at all (core#654).
     *
     * @return array<string, array<int, mixed>>
     */
    private function categoryRules(): array
    {
        $ids = array_map(
            static fn (TagResponseDTO $tag): string => $tag->id,
            app(ProposalTagOptionsInterface::class)->categoriesFor($this->type(), (string) $this->user()?->getAuthIdentifier()),
        );

        if ($ids === []) {
            return [];
        }

        return ['category' => [Rule::requiredIf(fn (): bool => $this->wantsToSubmit()), 'nullable', 'string', Rule::in($ids)]];
    }

    public function categoryId(): ?string
    {
        return $this->optionalString('category');
    }

    public function wantsToSubmit(): bool
    {
        return $this->boolean('submit');
    }

    public function removesImage(): bool
    {
        return $this->boolean('remove_image');
    }

    /**
     * @return list<string>
     */
    public function tagIds(): array
    {
        $tags = $this->validated('tags');

        return is_array($tags) ? array_values(array_filter($tags, is_string(...))) : [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['title', 'description', 'location', 'start_date', 'start_time', 'end_date', 'end_time', 'image', 'tags', 'category', 'note', 'excerpt', 'content'] as $field) {
            $attributes[$field] = (string) __('proposals.fields.'.$field);
        }

        return $attributes;
    }

    protected function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
