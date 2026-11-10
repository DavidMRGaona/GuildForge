<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media\Sources;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Infrastructure\Media\Sources\ArticleMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Media\ColumnSourceContractTests;
use Tests\TestCase;

final class ArticleMediaSourceTest extends TestCase
{
    use ColumnSourceContractTests;
    use LazilyRefreshDatabase;

    protected function source(): MediaUsageSourceInterface
    {
        return app(ArticleMediaSource::class);
    }

    protected function makeRecord(?string $path): Model
    {
        return ArticleModel::factory()->create(['featured_image_public_id' => $path]);
    }

    protected function updatePermission(): string
    {
        return 'articles.update';
    }

    public function test_declares_its_type_and_folders(): void
    {
        $this->assertSame('articles', $this->source()->type());
        $this->assertSame(['articles/'], $this->source()->folders());
    }
}
