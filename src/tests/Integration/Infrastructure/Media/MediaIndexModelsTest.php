<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class MediaIndexModelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function usage(): array
    {
        return [
            'file_id' => 'events/a',
            'source_type' => 'events',
            'module' => null,
            'record_id' => '1',
            'role' => 'image',
            'title' => 'Event',
        ];
    }

    public function test_usage_unique_key_rejects_duplicates(): void
    {
        MediaUsageModel::query()->create($this->usage());

        $this->expectException(QueryException::class);

        MediaUsageModel::query()->create($this->usage());
    }

    public function test_file_has_usages_by_file_id(): void
    {
        $file = MediaFileModel::query()->create([
            'file_id' => 'events/a',
            'resource_type' => 'image',
            'folder' => 'events/',
            'format' => 'jpg',
            'bytes' => 100,
            'uploaded_at' => now(),
            'last_seen_at' => now(),
        ]);
        MediaUsageModel::query()->create($this->usage());

        $this->assertCount(1, $file->usages);
    }

    public function test_deletion_keeps_row_when_user_is_deleted(): void
    {
        $user = UserModel::factory()->create();
        $deletion = MediaDeletionModel::query()->create([
            'file_id' => 'events/a',
            'resource_type' => 'image',
            'folder' => 'events/',
            'outcome' => 'deleted',
            'deleted_by' => $user->id,
            'deleted_at' => now(),
        ]);

        $this->assertSame($user->id, $deletion->deletedBy?->id);

        $user->forceDelete();

        $this->assertNull($deletion->fresh()?->deleted_by);
    }
}
