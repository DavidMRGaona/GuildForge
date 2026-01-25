<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use Database\Factories\ArticleModelFactory;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserModel $author
 * @property-read Collection<int, TagModel> $tags
 */
final class ArticleModel extends Model
{
    use DeletesCloudinaryImages;

    /** @use HasFactory<ArticleModelFactory> */
    use HasFactory;

    use HasUuids;

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
        ];
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'author_id');
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
            ->withTimestamps();
    }
}
