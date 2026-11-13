<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * A save in the panel is a Livewire request of the web group: while an impersonation lasts it
 * runs, and nothing of it stays.
 */
final class ImpersonationPanelWritesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    private UserModel $editor;

    private ArticleModel $article;

    private TagModel $category;

    protected function setUp(): void
    {
        parent::setUp();

        // The editor role with its permissions, articles.update among them
        $this->seed(RolesSeeder::class);
        $this->admin = UserModel::factory()->admin()->create();
        $this->editor = UserModel::factory()->editor()->create();
        $this->article = ArticleModel::factory()->create(['title' => 'Original title', 'author_id' => $this->editor->id]);
        $this->category = TagModel::factory()->forArticles()->create();
    }

    public function test_a_livewire_save_in_the_panel_does_not_persist(): void
    {
        $this->actingAs($this->admin);
        $this->app->make(ImpersonationServiceInterface::class)->start($this->admin, $this->editor, ImpersonationMode::Simulate);

        $this->saveTitle('Changed title')->assertOk();

        $this->assertSame('Original title', $this->article->fresh()?->title);
    }

    public function test_the_same_save_persists_without_an_impersonation(): void
    {
        $this->actingAs($this->editor);

        $this->saveTitle('Changed title')->assertOk();

        $this->assertSame('Changed title', $this->article->fresh()?->title);
    }

    /**
     * @return TestResponse<Response>
     */
    private function saveTitle(string $title): TestResponse
    {
        $html = (string) $this->get(ArticleResource::getUrl('edit', ['record' => $this->article]))->assertOk()->getContent();

        return $this->withHeaders(['X-Livewire' => 'true'])->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => $this->editPageSnapshot($html),
                'updates' => ['data.title' => $title, 'data.category_id' => $this->category->id],
                'calls' => [['path' => '', 'method' => 'save', 'params' => []]],
            ]],
        ]);
    }

    private function editPageSnapshot(string $html): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            $decoded = json_decode($snapshot, true);
            $name = is_array($decoded) ? ($decoded['memo']['name'] ?? '') : '';

            if (is_string($name) && $name === EditArticle::class) {
                return $snapshot;
            }
        }

        $this->fail('The edit page has no Livewire component of its own');
    }
}
