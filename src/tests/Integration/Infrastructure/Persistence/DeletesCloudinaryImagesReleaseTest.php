<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

/**
 * A record that lets go of an image releases it after the save: deleted only when nobody else uses it.
 */
final class DeletesCloudinaryImagesReleaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    private InMemoryMediaCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
    }

    public function test_changing_an_event_image_used_by_an_article_keeps_it(): void
    {
        $this->catalog->add('events/2026/11/shared');
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/shared.jpg']);
        ArticleModel::factory()->create(['featured_image_public_id' => 'events/2026/11/shared.jpg']);

        $event->update(['image_public_id' => null]);

        $this->assertArrayHasKey('events/2026/11/shared', $this->catalog->assets);
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_changing_an_unused_event_image_releases_it_after_save(): void
    {
        $this->catalog->add('events/2026/11/old');
        $this->catalog->add('events/2026/11/new');
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/old.jpg']);

        $event->update(['image_public_id' => 'events/2026/11/new.jpg']);

        $this->assertArrayNotHasKey('events/2026/11/old', $this->catalog->assets);
        $this->assertArrayHasKey('events/2026/11/new', $this->catalog->assets);
        $this->assertSame('automatic', MediaDeletionModel::query()->sole()->kind->value);
    }

    public function test_deleting_an_event_releases_its_image(): void
    {
        $this->catalog->add('events/2026/11/a');
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);

        $event->delete();

        $this->assertArrayNotHasKey('events/2026/11/a', $this->catalog->assets);
    }

    public function test_deleting_an_event_keeps_an_image_an_article_uses(): void
    {
        $this->catalog->add('events/2026/11/a');
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);
        ArticleModel::factory()->create(['featured_image_public_id' => 'events/2026/11/a.jpg']);

        $event->delete();

        $this->assertArrayHasKey('events/2026/11/a', $this->catalog->assets);
    }

    public function test_a_failed_save_releases_nothing(): void
    {
        $this->catalog->add('events/2026/11/a');
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);

        try {
            DB::transaction(static function () use ($event): void {
                $event->update(['image_public_id' => null]);

                throw new RuntimeException('save failed');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertArrayHasKey('events/2026/11/a', $this->catalog->assets);
    }

    public function test_a_source_that_throws_keeps_the_image(): void
    {
        $this->catalog->add('events/2026/11/a');
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);
        // The articles table is gone: its source cannot say whether it uses the file
        Schema::drop('articles');

        $event->update(['image_public_id' => null]);

        $this->assertArrayHasKey('events/2026/11/a', $this->catalog->assets);
    }

    public function test_two_fields_with_the_same_file_release_once_without_error(): void
    {
        Schema::create('two_image_records', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_image')->nullable();
            $table->string('second_image')->nullable();
        });
        $this->catalog->add('events/2026/11/a');
        $record = TwoImageRecord::query()->create(['first_image' => 'events/2026/11/a.jpg', 'second_image' => 'events/2026/11/a.jpg']);

        $record->delete();

        $this->assertArrayNotHasKey('events/2026/11/a', $this->catalog->assets);
        $this->assertSame(['deleted', 'missing'], MediaDeletionModel::query()->orderBy('outcome')->pluck('outcome')->all());
    }

    public function test_avatar_is_still_deleted_right_away(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('avatars/a.jpg', 'x');
        $user = UserModel::factory()->create(['avatar_public_id' => 'avatars/a.jpg']);

        $user->update(['avatar_public_id' => null]);

        Storage::disk('images')->assertMissing('avatars/a.jpg');
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }
}

/**
 * A record with two image fields, which may hold the same file.
 */
final class TwoImageRecord extends Model
{
    use DeletesCloudinaryImages;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'two_image_records';

    protected $guarded = [];

    /** @var list<string> */
    protected array $cloudinaryImageFields = ['first_image', 'second_image'];
}
