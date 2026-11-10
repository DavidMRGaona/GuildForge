<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Media\Sources;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Filament\Media\Sources\HeroSlideMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Media\ColumnSourceContractTests;
use Tests\TestCase;

final class HeroSlideMediaSourceTest extends TestCase
{
    use ColumnSourceContractTests;
    use LazilyRefreshDatabase;

    protected function source(): MediaUsageSourceInterface
    {
        return app(HeroSlideMediaSource::class);
    }

    protected function makeRecord(?string $path): Model
    {
        return HeroSlideModel::factory()->create(['image_public_id' => $path]);
    }

    protected function updatePermission(): string
    {
        return 'hero_slides.update';
    }

    public function test_declares_its_type_and_folders(): void
    {
        $this->assertSame('hero_slides', $this->source()->type());
        $this->assertSame(['hero-slides/'], $this->source()->folders());
    }
}
