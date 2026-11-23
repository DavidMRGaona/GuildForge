<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Filament\Media\Sources\ArticleMediaSource;
use App\Filament\Media\Sources\EventMediaSource;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

/**
 * A proposal draft never submitted belongs to its author alone (HidesUnsubmittedProposals): the media library
 * keeps its image and its title out of sight and never offers it for reuse, though the file still counts as
 * used (core#837). Once submitted, the panel sees it like any other proposal.
 */
final class UnsubmittedProposalMediaTest extends TestCase
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
    }

    public function test_the_image_of_an_unsubmitted_event_draft_is_concealed_and_not_offered(): void
    {
        $draft = EventModel::factory()->proposal()->create(['title' => 'Mi borrador secreto', 'image_public_id' => 'events/2026/11/draft.jpg']);
        $this->catalog->add('events/2026/11/draft');
        $this->sync();

        $this->assertConcealed('events/2026/11/draft', 'events', (string) $draft->id);
    }

    public function test_the_image_of_an_unsubmitted_article_draft_is_concealed_and_not_offered(): void
    {
        $draft = ArticleModel::factory()->proposal()->create(['title' => 'Mi borrador secreto', 'featured_image_public_id' => 'articles/2026/11/draft.jpg']);
        $this->catalog->add('articles/2026/11/draft');
        $this->sync();

        $this->assertConcealed('articles/2026/11/draft', 'articles', (string) $draft->id);
    }

    public function test_submitting_a_draft_shows_its_image_and_withdrawing_it_conceals_it_again(): void
    {
        $draft = EventModel::factory()->proposal()->create(['title' => 'Jornada de rol', 'image_public_id' => 'events/2026/11/p.jpg']);
        $this->catalog->add('events/2026/11/p');
        $this->sync();
        $this->assertTrue($this->library()->conceals($this->file('events/2026/11/p')));

        // As ProposalService::submit() does: through the model, so its observer refreshes the usages
        $this->saveProposal($draft, ProposalStatus::PendingReview, submitted: true);

        $this->assertFalse($this->library()->conceals($this->file('events/2026/11/p')));
        $this->assertSame(['events/2026/11/p'], $this->reusable());
        $this->assertSame('Jornada de rol', $this->usage('events', (string) $draft->id)->title);

        // As ProposalService::withdraw() does: the author's again
        $this->saveProposal($draft, ProposalStatus::Draft, submitted: false);

        $this->assertTrue($this->library()->conceals($this->file('events/2026/11/p')));
        $this->assertSame([], $this->reusable());
    }

    public function test_a_returned_draft_was_submitted_so_it_stays_in_sight(): void
    {
        $returned = EventModel::factory()->proposal(ProposalStatus::Draft, submitted: true)->create(['image_public_id' => 'events/2026/11/r.jpg']);
        $this->catalog->add('events/2026/11/r');
        $this->sync();

        $this->assertFalse($this->library()->conceals($this->file('events/2026/11/r')));
        $this->assertFalse($this->usage('events', (string) $returned->id)->is_hidden);
    }

    public function test_sources_give_no_edit_link_for_an_unsubmitted_draft(): void
    {
        $event = EventModel::factory()->proposal()->create(['image_public_id' => 'events/2026/11/e.jpg']);
        $article = ArticleModel::factory()->proposal()->create(['featured_image_public_id' => 'articles/2026/11/a.jpg']);
        $admin = UserModel::factory()->admin()->create();

        $this->assertNull(app(EventMediaSource::class)->editUrl((string) $event->id, $admin));
        $this->assertNull(app(ArticleMediaSource::class)->editUrl((string) $article->id, $admin));
    }

    private function assertConcealed(string $fileId, string $type, string $recordId): void
    {
        $usage = $this->usage($type, $recordId);
        $this->assertTrue($usage->is_hidden);
        // Its title is not given either
        $this->assertSame(__('filament.media_library.unsubmitted_proposal'), $usage->title);
        $this->assertTrue($this->library()->conceals($this->file($fileId)));
        $this->assertSame([], $this->reusable());
    }

    private function saveProposal(Model $proposal, ProposalStatus $status, bool $submitted): void
    {
        $proposal->forceFill(['proposal_status' => $status, 'submitted_at' => $submitted ? now() : null]);
        $proposal->save();
    }

    private function sync(): void
    {
        $result = app(MediaIndexerInterface::class)->sync();
        $this->assertSame([], $result->failedSources);
    }

    /**
     * @return list<string>
     */
    private function reusable(): array
    {
        $library = $this->library();

        /** @var list<string> */
        return $library->applyReusable($library->query())->pluck('file_id')->all();
    }

    private function file(string $id): MediaFileModel
    {
        return MediaFileModel::query()->where('file_id', $id)->firstOrFail();
    }

    private function usage(string $type, string $recordId): MediaUsageModel
    {
        return MediaUsageModel::query()->where('source_type', $type)->where('record_id', $recordId)->firstOrFail();
    }

    private function library(): MediaLibraryQueryInterface
    {
        return app(MediaLibraryQueryInterface::class);
    }
}
