<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media\Sources;

use App\Domain\Media\ValueObjects\MediaFileId;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Media\FakeSoftDeletingMediaSource;
use Tests\Support\Media\FakeSoftDeletingModel;
use Tests\TestCase;

final class EloquentColumnMediaSourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeSoftDeletingModel::createTable();
    }

    public function test_soft_deleted_records_count(): void
    {
        $record = FakeSoftDeletingModel::query()->create(['title' => 'Gone', 'image' => 'fake/a.jpg']);
        $record->delete();
        $source = app(FakeSoftDeletingMediaSource::class);

        $this->assertCount(1, iterator_to_array($source->usages(), false));
        $this->assertCount(1, $source->usagesFor((string) $record->getKey()));
        $this->assertTrue($source->usesFile(new MediaFileId('fake/a')));
    }

    public function test_usages_are_read_in_more_than_one_chunk(): void
    {
        foreach (range(1, 520) as $number) {
            FakeSoftDeletingModel::query()->create(['image' => "fake/{$number}.jpg"]);
        }

        $this->assertCount(520, iterator_to_array(app(FakeSoftDeletingMediaSource::class)->usages(), false));
    }
}
