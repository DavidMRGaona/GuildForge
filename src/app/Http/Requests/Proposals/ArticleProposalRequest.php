<?php

declare(strict_types=1);

namespace App\Http\Requests\Proposals;

use App\Application\Proposals\DTOs\ArticleProposalDataDTO;
use App\Domain\Proposals\Enums\ProposalType;

/**
 * An article proposal: title, excerpt, content, featured image and tags. No author, slug or
 * publication date: the server sets them.
 */
final class ArticleProposalRequest extends ProposalRequest
{
    protected function type(): ProposalType
    {
        return ProposalType::Article;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->commonRules();
        // Articles have no private note: the reviewer reads the text itself
        unset($rules['note']);

        return [
            ...$rules,
            'excerpt' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:500000'],
        ];
    }

    public function toDto(?string $featuredImagePublicId): ArticleProposalDataDTO
    {
        $title = $this->validated('title');
        $content = $this->validated('content');

        return new ArticleProposalDataDTO(
            title: is_string($title) ? $title : '',
            content: is_string($content) ? $content : '',
            excerpt: $this->optionalString('excerpt'),
            featuredImagePublicId: $featuredImagePublicId,
            tagIds: $this->tagIds(),
        );
    }
}
