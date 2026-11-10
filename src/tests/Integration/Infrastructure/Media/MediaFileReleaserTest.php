<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\DTOs\MediaDeletionResultDTO;
use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Jobs\ReleaseMediaFileJob;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class MediaFileReleaserTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<array{string, string, ?string}> */
    private array $released = [];

    private ?MediaDeletionRefusal $answer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        $test = $this;
        $this->app->instance(MediaDeletionServiceInterface::class, new readonly class($test) implements MediaDeletionServiceInterface
        {
            public function __construct(private MediaFileReleaserTest $test) {}

            public function isEnabled(): bool
            {
                return false;
            }

            public function delete(array $fileIds, object $actor): MediaDeletionResultDTO
            {
                throw new RuntimeException('not used');
            }

            public function releaseIfUnused(MediaFileId $id, string $resourceType, ?string $actorId): ?MediaDeletionRefusal
            {
                return $this->test->record($id->value, $resourceType, $actorId);
            }
        });
    }

    public function record(string $fileId, string $resourceType, ?string $actorId): ?MediaDeletionRefusal
    {
        $this->released[] = [$fileId, $resourceType, $actorId];

        return $this->answer;
    }

    public function test_release_dispatches_the_job_after_commit_with_the_actor(): void
    {
        $actor = UserModel::factory()->create();
        $this->actingAs($actor);

        DB::transaction(function (): void {
            $this->releaser()->release('events/2026/11/a.jpg');

            $this->assertSame([], $this->released, 'Nothing is released before the commit');
        });

        $this->assertSame([['events/2026/11/a', 'image', (string) $actor->getKey()]], $this->released);
    }

    public function test_release_inside_a_rolled_back_transaction_dispatches_nothing(): void
    {
        try {
            DB::transaction(function (): void {
                $this->releaser()->release('events/a.jpg');

                throw new RuntimeException('save failed');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertSame([], $this->released);
    }

    public function test_release_of_an_unresolvable_path_does_nothing(): void
    {
        $this->releaser()->release('');
        // Another host's URL is not a file of this site
        $this->releaser()->release('https://example.com/elsewhere/a.jpg');

        $this->assertSame([], $this->released);
    }

    public function test_job_retries_when_the_catalog_fails(): void
    {
        $this->answer = MediaDeletionRefusal::CatalogFailed;
        $job = (new ReleaseMediaFileJob('events/a', 'image', null))->withFakeQueueInteractions();

        $job->handle($this->app->make(MediaDeletionServiceInterface::class));

        $job->assertReleased(60);
    }

    public function test_job_ends_quietly_on_any_other_refusal(): void
    {
        $this->answer = MediaDeletionRefusal::InUse;
        $job = (new ReleaseMediaFileJob('events/a', 'image', null))->withFakeQueueInteractions();

        $job->handle($this->app->make(MediaDeletionServiceInterface::class));

        $job->assertNotReleased();
        $job->assertNotFailed();
    }

    private function releaser(): MediaFileReleaserInterface
    {
        return $this->app->make(MediaFileReleaserInterface::class);
    }
}
