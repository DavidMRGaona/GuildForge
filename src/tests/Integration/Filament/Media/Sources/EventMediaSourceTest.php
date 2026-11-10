<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Media\Sources;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
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
}
