<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\CannotPublishWithoutAuthorException;
use App\Domain\Proposals\Exceptions\ProposalNotApprovedException;
use App\Domain\Proposals\ValueObjects\ProposalReview;
use App\Domain\ValueObjects\ArticleId;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;

final class Article
{
    public function __construct(
        private readonly ArticleId $id,
        private readonly string $title,
        private readonly Slug $slug,
        private readonly string $content,
        private readonly ?string $authorId,
        private readonly ?string $excerpt = null,
        private readonly ?string $featuredImagePublicId = null,
        private bool $isPublished = false,
        private ?DateTimeImmutable $publishedAt = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $updatedAt = null,
        private readonly ?ProposalReview $proposal = null,
    ) {}

    public function id(): ArticleId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function excerpt(): ?string
    {
        return $this->excerpt;
    }

    public function featuredImagePublicId(): ?string
    {
        return $this->featuredImagePublicId;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function authorId(): ?string
    {
        return $this->authorId;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * The review data when the article was proposed from the web; null when created in the panel.
     * The proposal stays its proposer's even if the displayed author changes.
     */
    public function proposal(): ?ProposalReview
    {
        return $this->proposal;
    }

    public function publish(): void
    {
        if ($this->proposal instanceof ProposalReview && $this->proposal->isUnapproved()) {
            throw ProposalNotApprovedException::cannotPublish();
        }

        if ($this->authorId === null) {
            throw CannotPublishWithoutAuthorException::create();
        }

        $this->isPublished = true;
        $this->publishedAt = new DateTimeImmutable;
    }

    public function unpublish(): void
    {
        $this->isPublished = false;
        $this->publishedAt = null;
    }
}
