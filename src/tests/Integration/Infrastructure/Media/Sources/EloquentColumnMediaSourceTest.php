<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media\Sources;

use App\Application\Media\DTOs\MediaUsageDTO;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use Illuminate\Database\Eloquent\Model;
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

    public function test_usages_are_shown_unless_a_subclass_hides_them(): void
    {
        $shown = FakeSoftDeletingModel::query()->create(['title' => 'Primera partida', 'image' => 'fake/a.jpg']);
        $secret = FakeSoftDeletingModel::query()->create(['title' => 'Secreto', 'image' => 'fake/b.jpg']);

        $this->assertFalse(app(FakeSoftDeletingMediaSource::class)->usagesFor((string) $secret->getKey())[0]->hidden);

        $source = new class(app(MediaFileIdResolverInterface::class)) extends EloquentColumnMediaSource
        {
            public function type(): string
            {
                return 'fake.secrets';
            }

            public function label(): string
            {
                return 'Secretos';
            }

            public function folders(): array
            {
                return ['fake/'];
            }

            protected function modelClass(): string
            {
                return FakeSoftDeletingModel::class;
            }

            protected function column(): string
            {
                return 'image';
            }

            protected function role(): string
            {
                return 'Insignia';
            }

            protected function titleOf(Model $record): string
            {
                return (string) $record->getAttribute('title');
            }

            protected function editUrlOf(Model $record, object $user): ?string
            {
                return null;
            }

            protected function isHidden(Model $record): bool
            {
                return $record->getAttribute('title') === 'Secreto';
            }
        };

        $hidden = [];
        foreach ($source->usages() as $usage) {
            $hidden[$usage->recordId] = $usage->hidden;
        }

        $this->assertSame([(string) $shown->getKey() => false, (string) $secret->getKey() => true], $hidden);
        $this->assertTrue($source->usagesFor((string) $secret->getKey())[0]->hidden);
        $this->assertContainsOnlyInstancesOf(MediaUsageDTO::class, $source->usagesFor((string) $shown->getKey()));
    }
}
