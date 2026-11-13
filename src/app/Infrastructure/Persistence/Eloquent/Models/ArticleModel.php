<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasProposalReview;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Database\Factories\ArticleModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $excerpt
 * @property string|null $featured_image_public_id
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property string $author_id
 * @property ProposalStatus|null $proposal_status
 * @property string|null $proposed_by
 * @property string|null $proposal_note
 * @property string|null $proposal_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserModel $author
 * @property-read Collection<int, TagModel> $tags
 * @property-read UserModel|null $proposer
 * @property-read UserModel|null $reviewer
 *
 * @method static Builder<ArticleModel> includingUnsubmittedProposals()
 * @method static Builder<ArticleModel> unapprovedProposals()
 * @method static Builder<ArticleModel> orderedByPublication()
 */
final class ArticleModel extends Model
{
    use DeletesCloudinaryImages;

    /** @use HasFactory<ArticleModelFactory> */
    use HasFactory;

    use HasProposalReview;
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    /** @var array<string> */
    protected array $cloudinaryImageFields = ['featured_image_public_id'];

    protected $table = 'articles';

    protected $fillable = [
        'id',
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image_public_id',
        'is_published',
        'published_at',
        'author_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'proposal_status' => ProposalStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'author_id');
    }

    /**
     * An unapproved proposal has no publication date either.
     */
    protected function keepProposalUnpublished(): void
    {
        $this->setAttribute('published_at', null);
    }

    protected static function newFactory(): ArticleModelFactory
    {
        return ArticleModelFactory::new();
    }

    /**
     * @return BelongsToMany<TagModel, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TagModel::class, 'article_tag', 'article_id', 'tag_id')
            ->withTimestamps()
            ->ordered();
    }

    /**
     * Scope to order articles newest first, with a stable order for articles published at the same time.
     *
     * @param  Builder<ArticleModel>  $query
     * @return Builder<ArticleModel>
     */
    public function scopeOrderedByPublication(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('created_at')->orderByDesc('id');
    }
}
