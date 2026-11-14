<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

/**
 * What a person may write in an article proposal: no author, slug nor publication.
 */
final readonly class ArticleProposalDataDTO
{
    /**
     * @param  list<string>  $tagIds  tags that do not exist or do not apply to articles are ignored
     * @param  ?string  $note  private note for the administration
     * @param  ?string  $categoryId  only taken from whoever may publish articles directly
     *                               (ProposalTagOptionsInterface); null keeps the stored category
     */
    public function __construct(
        public string $title,
        public string $content,
        public ?string $excerpt = null,
        public ?string $featuredImagePublicId = null,
        public array $tagIds = [],
        public ?string $note = null,
        public ?string $categoryId = null,
    ) {}
}
