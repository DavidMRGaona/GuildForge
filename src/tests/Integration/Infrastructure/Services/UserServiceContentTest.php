<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class UserServiceContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserServiceInterface $service;

    private SettingsServiceInterface $settingsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserServiceInterface::class);
        $this->settingsService = app(SettingsServiceInterface::class);
    }

    public function test_count_user_content_returns_article_count(): void
    {
        $user = UserModel::factory()->create();
        ArticleModel::factory()->count(5)->create(['author_id' => $user->id]);

        $result = $this->service->countUserContent($user->id);

        $this->assertEquals(['articles' => 5], $result);
    }

    public function test_count_user_content_returns_zero_for_user_with_no_content(): void
    {
        $user = UserModel::factory()->create();

        $result = $this->service->countUserContent($user->id);

        $this->assertEquals(['articles' => 0], $result);
    }

    public function test_count_user_content_only_counts_articles_by_specified_user(): void
    {
        $user1 = UserModel::factory()->create();
        $user2 = UserModel::factory()->create();

        ArticleModel::factory()->count(3)->create(['author_id' => $user1->id]);
        ArticleModel::factory()->count(7)->create(['author_id' => $user2->id]);

        $result = $this->service->countUserContent($user1->id);

        $this->assertEquals(['articles' => 3], $result);
    }

    public function test_anonymize_with_content_transfer_transfers_articles_to_target_user(): void
    {
        $sourceUser = UserModel::factory()->create();
        $targetUser = UserModel::factory()->create();

        $articles = ArticleModel::factory()->count(3)->create(['author_id' => $sourceUser->id]);

        $dto = new AnonymizeUserDTO(
            userId: $sourceUser->id,
            contentAction: 'transfer',
            transferToUserId: $targetUser->id,
        );

        $this->service->anonymizeWithContentTransfer($dto);

        foreach ($articles as $article) {
            $article->refresh();
            $this->assertEquals($targetUser->id, $article->author_id);
        }

        $sourceUser->refresh();
        $this->assertNotNull($sourceUser->anonymized_at);
    }

    public function test_anonymize_with_content_transfer_keeps_content_with_anonymized_user(): void
    {
        $user = UserModel::factory()->create();
        $articles = ArticleModel::factory()->count(3)->create(['author_id' => $user->id]);

        $dto = new AnonymizeUserDTO(
            userId: $user->id,
            contentAction: 'anonymize',
            transferToUserId: null,
        );

        $this->service->anonymizeWithContentTransfer($dto);

        foreach ($articles as $article) {
            $article->refresh();
            $this->assertEquals($user->id, $article->author_id);
        }

        $user->refresh();
        $this->assertNotNull($user->anonymized_at);
    }

    public function test_anonymize_with_content_transfer_uses_configured_anonymous_name(): void
    {
        $user = UserModel::factory()->create();

        $this->settingsService->set('anonymized_user_name', 'Redacción');

        $dto = new AnonymizeUserDTO(
            userId: $user->id,
            contentAction: 'anonymize',
            transferToUserId: null,
        );

        $this->service->anonymizeWithContentTransfer($dto);

        $user->refresh();
        $this->assertEquals('Redacción', $user->name);
    }

    public function test_anonymize_with_content_transfer_works_when_user_has_no_articles(): void
    {
        $sourceUser = UserModel::factory()->create();
        $targetUser = UserModel::factory()->create();

        $dto = new AnonymizeUserDTO(
            userId: $sourceUser->id,
            contentAction: 'transfer',
            transferToUserId: $targetUser->id,
        );

        $this->service->anonymizeWithContentTransfer($dto);

        $sourceUser->refresh();
        $this->assertNotNull($sourceUser->anonymized_at);
    }
}
