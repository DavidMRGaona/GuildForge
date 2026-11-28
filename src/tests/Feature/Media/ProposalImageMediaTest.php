<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Exceptions\InvalidProposalDataException;
use App\Infrastructure\Media\Jobs\ReleaseMediaFileJob;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use ArrayObject;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

/**
 * Images uploaded from the web proposal forms live in a folder of their own (`events/proposals/`,
 * `articles/proposals/`), whose source conceals the files nobody uses: a draft's image that could not be let go
 * of never shows in the library, while the unused files of the panel's folders keep their thumbnail for review.
 * A save the service refuses lets go of the image it uploaded at once (core#841).
 */
final class ProposalImageMediaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private InMemoryMediaCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
        $this->configure(maxDrafts: 5);
    }

    public function test_a_refused_event_proposal_lets_go_of_its_image_at_once(): void
    {
        $this->configure(maxDrafts: 1);
        $user = UserModel::factory()->create();
        EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create();

        $this->actingAs($user)->from('/propuestas/eventos/nuevo')
            ->post('/propuestas/eventos', $this->eventForm(['image' => UploadedFile::fake()->image('cartel.png')]))
            ->assertRedirect('/propuestas/eventos/nuevo')
            ->assertSessionHas('error');

        // The release deletes it: nobody uses it, and its deletion does not name it
        $deletion = MediaDeletionModel::query()->sole();
        $this->assertMatchesRegularExpression('~^events/proposals/\d{4}/\d{2}/[0-9a-f-]{36}$~', $deletion->file_id);
        $this->assertTrue($deletion->is_hidden);
    }

    public function test_a_refused_article_proposal_lets_go_of_its_image_at_once(): void
    {
        $this->configure(maxDrafts: 1);
        $user = UserModel::factory()->create();
        ArticleModel::factory()->proposal(ProposalStatus::Draft, $user)->create();
        $releaser = $this->spyOnReleases();

        $this->actingAs($user)->from('/propuestas/articulos/nuevo')
            ->post('/propuestas/articulos', $this->articleForm(['image' => UploadedFile::fake()->image('portada.png')]))
            ->assertSessionHas('error');

        $releaser->shouldHaveReceived('release')->once()->with(Mockery::pattern('~^articles/proposals/\d{4}/\d{2}/[0-9a-f-]{36}\.png$~'));
    }

    public function test_a_refused_change_lets_go_of_the_new_image_and_keeps_the_current_one(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create(['image_public_id' => 'events/proposals/2026/11/current.jpg']);
        $releaser = $this->spyOnReleases();
        $this->mock(ProposalServiceInterface::class, static function (MockInterface $service): void {
            $service->shouldReceive('updateEvent')->andThrow(InvalidProposalDataException::field('title'));
        });

        $this->actingAs($user)->from("/propuestas/eventos/{$event->id}/editar")
            ->put("/propuestas/eventos/{$event->id}", $this->eventForm(['image' => UploadedFile::fake()->image('otro.png')]))
            ->assertSessionHas('error');

        $releaser->shouldHaveReceived('release')->once()->with(Mockery::pattern('~^events/proposals/\d{4}/\d{2}/[0-9a-f-]{36}\.png$~'));
        $releaser->shouldNotHaveReceived('release', ['events/proposals/2026/11/current.jpg']);
    }

    public function test_a_saved_proposal_keeps_its_image(): void
    {
        $releaser = $this->spyOnReleases();

        $this->actingAs(UserModel::factory()->create())
            ->post('/propuestas/eventos', $this->eventForm(['image' => UploadedFile::fake()->image('cartel.png')]))
            ->assertRedirect('/perfil?tab=proposals');

        $releaser->shouldNotHaveReceived('release');
        $path = (string) EventModel::query()->includingUnsubmittedProposals()->sole()->image_public_id;
        Storage::disk('images')->assertExists($path);
    }

    public function test_a_save_that_throws_after_storing_the_record_keeps_its_image(): void
    {
        // The release only deletes what a live check finds unused: the stored draft still uses it
        $user = UserModel::factory()->create();
        $this->mock(ProposalServiceInterface::class, static function (MockInterface $service) use ($user): void {
            $service->shouldReceive('createEvent')->andReturnUsing(static function (string $userId, $data) use ($user): string {
                EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create(['image_public_id' => $data->imagePublicId]);

                throw new RuntimeException('failed after saving');
            });
        });
        $released = $this->recordReleases();
        $checked = $this->recordReleaseChecks();

        $this->actingAs($user)
            ->post('/propuestas/eventos', $this->eventForm(['image' => UploadedFile::fake()->image('cartel.png')]))
            ->assertServerError();

        $path = (string) EventModel::query()->includingUnsubmittedProposals()->sole()->image_public_id;
        $this->assertMatchesRegularExpression('~^events/proposals/\d{4}/\d{2}/[0-9a-f-]{36}\.png$~', $path);
        // The failed save let go of its image, and the live check ran and kept it
        $this->assertSame([$path], $released->getArrayCopy());
        $this->assertSame([substr($path, 0, -4)], $checked->getArrayCopy());
        $this->assertSame([], $this->catalog->destroyed);
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_a_refused_proposal_while_simulating_an_account_releases_nothing(): void
    {
        // The safety net rolls the request back and neutralises the queue: the release is asked for, yet no
        // check runs and nothing is deleted
        $this->configure(maxDrafts: 1);
        $admin = UserModel::factory()->admin()->create();
        $target = UserModel::factory()->create();
        EventModel::factory()->proposal(ProposalStatus::Draft, $target)->create();
        $this->actingAs($admin);
        $this->app->make(ImpersonationServiceInterface::class)->start($admin, $target, ImpersonationMode::Simulate);
        $released = $this->recordReleases();
        $checked = $this->recordReleaseChecks();

        $this->from('/propuestas/eventos/nuevo')
            ->post('/propuestas/eventos', $this->eventForm(['image' => UploadedFile::fake()->image('cartel.png')]))
            ->assertSessionHas('error');

        $this->assertCount(1, $released);
        $this->assertMatchesRegularExpression('~^events/proposals/\d{4}/\d{2}/[0-9a-f-]{36}\.png$~', (string) $released[0]);
        $this->assertSame([], $checked->getArrayCopy());
        $this->assertSame([], $this->catalog->destroyed);
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_a_deletion_whose_concealment_cannot_be_told_is_recorded_hidden(): void
    {
        // Any doubt conceals it: the log never names a file that may be private
        $this->catalog->add('events/2026/11/replaced', uploadedAt: '2026-01-01 10:00:00');
        $this->sync();
        $this->mock(MediaLibraryQueryInterface::class, static function (MockInterface $library): void {
            $library->shouldReceive('conceals')->andThrow(new RuntimeException('unreadable'));
        });

        app(MediaFileReleaserInterface::class)->release('events/2026/11/replaced.jpg');

        $this->assertTrue(MediaDeletionModel::query()->sole()->is_hidden);
    }

    public function test_an_unused_proposal_image_is_concealed_while_an_unused_panel_image_shows(): void
    {
        // Declaring the marker on the events source would hide every replaced cover too: only the proposals' folder does
        $this->catalog->add('events/proposals/2026/11/lost');
        $this->catalog->add('articles/proposals/2026/11/lost');
        $this->catalog->add('events/2026/11/replaced');
        $this->sync();

        $library = $this->library();
        $this->assertTrue($library->conceals($this->file('events/proposals/2026/11/lost')));
        $this->assertTrue($library->conceals($this->file('articles/proposals/2026/11/lost')));
        $this->assertFalse($library->conceals($this->file('events/2026/11/replaced')));
        $this->assertSame(['events/2026/11/replaced'], $library->applyReusable($library->query())->pluck('file_id')->all());
    }

    public function test_the_image_of_an_approved_proposal_shows_and_can_be_reused(): void
    {
        $event = EventModel::factory()->proposal(ProposalStatus::Approved)->create(['image_public_id' => 'events/proposals/2026/11/approved.jpg']);
        $this->catalog->add('events/proposals/2026/11/approved');
        $this->sync();

        $library = $this->library();
        $this->assertFalse($library->conceals($this->file('events/proposals/2026/11/approved')));
        $this->assertSame(['events/proposals/2026/11/approved'], $library->applyReusable($library->query())->pluck('file_id')->all());
        $this->assertSame(['events'], $this->file('events/proposals/2026/11/approved')->usages()->pluck('source_type')->all());
        $this->assertSame($event->id, $this->file('events/proposals/2026/11/approved')->usages()->value('record_id'));
    }

    public function test_the_deletion_of_a_visible_file_keeps_its_name(): void
    {
        $this->catalog->add('events/2026/11/replaced', uploadedAt: '2026-01-01 10:00:00');
        $this->sync();

        app(MediaFileReleaserInterface::class)->release('events/2026/11/replaced.jpg');

        $deletion = MediaDeletionModel::query()->sole();
        $this->assertSame('events/2026/11/replaced', $deletion->file_id);
        $this->assertFalse($deletion->is_hidden);
    }

    private function configure(int $maxDrafts): void
    {
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(enabled: true),
            articles: new ProposalTypeSettingsDTO(enabled: true),
            maxOpenDrafts: $maxDrafts,
        ));
    }

    /**
     * Every path the real releaser is asked to let go of, in order.
     *
     * @return ArrayObject<int, string>
     */
    private function recordReleases(): ArrayObject
    {
        /** @var ArrayObject<int, string> $released */
        $released = new ArrayObject;
        $real = $this->app->make(MediaFileReleaserInterface::class);
        $this->app->instance(MediaFileReleaserInterface::class, new readonly class($real, $released) implements MediaFileReleaserInterface
        {
            /**
             * @param  ArrayObject<int, string>  $released
             */
            public function __construct(
                private MediaFileReleaserInterface $real,
                private ArrayObject $released,
            ) {}

            public function release(string $storedPath): void
            {
                $this->released->append($storedPath);
                $this->real->release($storedPath);
            }
        });

        return $released;
    }

    /**
     * The file id of every live check of a released file that ran, in order.
     *
     * @return ArrayObject<int, string>
     */
    private function recordReleaseChecks(): ArrayObject
    {
        /** @var ArrayObject<int, string> $checked */
        $checked = new ArrayObject;
        Queue::after(static function (JobProcessed $event) use ($checked): void {
            $job = unserialize($event->job->payload()['data']['command']);

            if ($job instanceof ReleaseMediaFileJob) {
                $checked->append($job->fileId);
            }
        });

        return $checked;
    }

    private function spyOnReleases(): MockInterface
    {
        $releaser = Mockery::spy(MediaFileReleaserInterface::class);
        $this->app->instance(MediaFileReleaserInterface::class, $releaser);

        return $releaser;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventForm(array $overrides = []): array
    {
        return [
            'title' => 'Jornada de rol',
            'description' => '<p>Ven a jugar</p>',
            'location' => 'Local de la asociación',
            'start_date' => '2026-12-05',
            'start_time' => '17:00',
            'end_date' => '2026-12-05',
            'end_time' => '21:30',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function articleForm(array $overrides = []): array
    {
        return [
            'title' => 'Crónica del torneo',
            'excerpt' => 'Un resumen',
            'content' => '<p>Fue genial</p>',
            ...$overrides,
        ];
    }

    private function sync(): void
    {
        $this->assertSame([], app(MediaIndexerInterface::class)->sync()->failedSources);
    }

    private function file(string $id): MediaFileModel
    {
        return MediaFileModel::query()->where('file_id', $id)->firstOrFail();
    }

    private function library(): MediaLibraryQueryInterface
    {
        return app(MediaLibraryQueryInterface::class);
    }
}
