<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Media\Sources;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Media\Sources\EventMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Media\ColumnSourceContractTests;
use Tests\TestCase;

final class EventMediaSourceTest extends TestCase
{
    use ColumnSourceContractTests;
    use LazilyRefreshDatabase;

    protected function source(): MediaUsageSourceInterface
    {
        return app(EventMediaSource::class);
    }

    protected function makeRecord(?string $path): Model
    {
        return EventModel::factory()->create(['image_public_id' => $path]);
    }

    protected function updatePermission(): string
    {
        return 'events.update';
    }

    public function test_declares_its_type_and_folders(): void
    {
        $this->assertSame('events', $this->source()->type());
        $this->assertSame(['events/'], $this->source()->folders());
    }

    public function test_the_image_of_a_draft_hidden_from_the_panel_still_counts(): void
    {
        $draft = EventModel::factory()->proposal()->create(['image_public_id' => 'events/2026/11/draft.jpg']);

        $this->assertTrue($this->source()->usesFile(new MediaFileId('events/2026/11/draft')));
        $this->assertCount(1, $this->source()->usagesFor($draft->id));
    }
}
