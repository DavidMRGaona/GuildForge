<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Factories;

use App\Infrastructure\Factories\EloquentResponseDTOFactory;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class EloquentResponseDTOFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    private EloquentResponseDTOFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new EloquentResponseDTOFactory;
    }

    public function test_create_event_dto_sanitizes_html_description(): void
    {
        $event = EventModel::factory()->published()->create([
            'description' => '<p>Safe content</p><script>alert("XSS")</script>',
        ]);

        $dto = $this->factory->createEventDTO($event);

        $this->assertStringContainsString('<p>Safe content</p>', $dto->description);
        $this->assertStringNotContainsString('<script>', $dto->description);
        $this->assertStringNotContainsString('alert', $dto->description);
    }

    public function test_create_event_dto_preserves_safe_html_tags(): void
    {
        $event = EventModel::factory()->published()->create([
            'description' => '<p>Text with <strong>bold</strong> and <em>italic</em></p>',
        ]);

        $dto = $this->factory->createEventDTO($event);

        $this->assertStringContainsString('<p>Text with <strong>bold</strong> and <em>italic</em></p>', $dto->description);
    }

    public function test_create_article_dto_sanitizes_html_content(): void
    {
        $user = UserModel::factory()->create();
        $article = ArticleModel::factory()->published()->create([
            'author_id' => $user->id,
            'content' => '<p>Safe article content</p><script>alert("XSS")</script>',
        ]);
        $article->load('author');

        $dto = $this->factory->createArticleDTO($article);

        $this->assertStringContainsString('<p>Safe article content</p>', $dto->content);
        $this->assertStringNotContainsString('<script>', $dto->content);
        $this->assertStringNotContainsString('alert', $dto->content);
    }

    public function test_create_user_dto_carries_created_at_in_the_display_zone(): void
    {
        $this->useUtcStorage();
        $user = UserModel::factory()->create(['created_at' => new DateTimeImmutable('2026-07-01T10:00:00Z')]);

        $dto = $this->factory->createUserDTO($user->fresh() ?? $user);

        $this->assertSame('Europe/Madrid', $dto->createdAt->getTimezone()->getName());
        $this->assertSame('2026-07-01T12:00:00+02:00', $dto->jsonSerialize()['createdAt']);
    }
}
