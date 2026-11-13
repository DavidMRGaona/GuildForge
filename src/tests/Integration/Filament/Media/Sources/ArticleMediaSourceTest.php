<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Media\Sources;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Media\Sources\ArticleMediaSource;
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

    public function test_the_image_of_a_draft_hidden_from_the_panel_still_counts(): void
    {
        $draft = ArticleModel::factory()->proposal()->create(['featured_image_public_id' => 'articles/2026/11/draft.jpg']);

        $this->assertTrue($this->source()->usesFile(new MediaFileId('articles/2026/11/draft')));
        $this->assertCount(1, $this->source()->usagesFor($draft->id));
    }
}
