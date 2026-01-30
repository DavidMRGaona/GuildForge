<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\SlugRedirect;
use App\Domain\Repositories\SlugRedirectRepositoryInterface;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\SlugRedirectModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSlugRedirectRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class EloquentSlugRedirectRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private EloquentSlugRedirectRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentSlugRedirectRepository();
    }

    public function test_it_implements_interface(): void
    {
        $this->assertInstanceOf(SlugRedirectRepositoryInterface::class, $this->repository);
    }

    public function test_find_by_old_slug_and_type_returns_redirect(): void
    {
        SlugRedirectModel::query()->create([
            'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'old_slug' => 'old-article-slug',
            'new_slug' => 'new-article-slug',
            'entity_type' => 'article',
            'entity_id' => 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
        ]);

        $redirect = $this->repository->findByOldSlugAndType(
            new Slug('old-article-slug'),
            'article'
        );

        $this->assertNotNull($redirect);
        $this->assertEquals('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $redirect->id());
        $this->assertEquals('old-article-slug', $redirect->oldSlug()->value);
        $this->assertEquals('new-article-slug', $redirect->newSlug()->value);
        $this->assertEquals('article', $redirect->entityType());
        $this->assertEquals('b2c3d4e5-f6a7-8901-bcde-f23456789012', $redirect->entityId());
    }

    public function test_find_by_old_slug_and_type_returns_null_when_not_found(): void
    {
        $redirect = $this->repository->findByOldSlugAndType(
            new Slug('non-existent-slug'),
            'article'
        );

        $this->assertNull($redirect);
    }

    public function test_find_by_old_slug_and_type_respects_entity_type(): void
    {
        SlugRedirectModel::query()->create([
            'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'old_slug' => 'same-slug',
            'new_slug' => 'new-article-slug',
            'entity_type' => 'article',
            'entity_id' => 'eeeeeeee-1111-1111-1111-111111111111',
        ]);
        SlugRedirectModel::query()->create([
            'id' => 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
            'old_slug' => 'same-slug',
            'new_slug' => 'new-event-slug',
            'entity_type' => 'event',
            'entity_id' => 'eeeeeeee-2222-2222-2222-222222222222',
        ]);

        $articleRedirect = $this->repository->findByOldSlugAndType(
            new Slug('same-slug'),
            'article'
        );
        $eventRedirect = $this->repository->findByOldSlugAndType(
            new Slug('same-slug'),
            'event'
        );

        $this->assertNotNull($articleRedirect);
        $this->assertEquals('new-article-slug', $articleRedirect->newSlug()->value);

        $this->assertNotNull($eventRedirect);
        $this->assertEquals('new-event-slug', $eventRedirect->newSlug()->value);
    }

    public function test_save_creates_new_redirect(): void
    {
        $redirect = new SlugRedirect(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            oldSlug: new Slug('old-slug'),
            newSlug: new Slug('new-slug'),
            entityType: 'article',
            entityId: 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
            createdAt: new DateTimeImmutable(),
        );

        $this->repository->save($redirect);

        $this->assertDatabaseHas('slug_redirects', [
            'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'old_slug' => 'old-slug',
            'new_slug' => 'new-slug',
            'entity_type' => 'article',
            'entity_id' => 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
        ]);
    }

    public function test_save_updates_existing_redirect(): void
    {
        SlugRedirectModel::query()->create([
            'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'old_slug' => 'old-slug',
            'new_slug' => 'intermediate-slug',
            'entity_type' => 'article',
            'entity_id' => 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
        ]);

        $redirect = new SlugRedirect(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            oldSlug: new Slug('old-slug'),
            newSlug: new Slug('final-slug'),
            entityType: 'article',
            entityId: 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
            createdAt: new DateTimeImmutable(),
        );

        $this->repository->save($redirect);

        $this->assertDatabaseHas('slug_redirects', [
            'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'old_slug' => 'old-slug',
            'new_slug' => 'final-slug',
        ]);
        $this->assertDatabaseCount('slug_redirects', 1);
    }

    public function test_update_all_pointing_to_updates_target_slugs(): void
    {
        // Create redirects pointing to 'slug-b'
        SlugRedirectModel::query()->create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'old_slug' => 'slug-a',
            'new_slug' => 'slug-b',
            'entity_type' => 'article',
            'entity_id' => 'eeeeeeee-1111-1111-1111-111111111111',
        ]);
        SlugRedirectModel::query()->create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'old_slug' => 'slug-x',
            'new_slug' => 'slug-b',
            'entity_type' => 'article',
            'entity_id' => 'eeeeeeee-1111-1111-1111-111111111111',
        ]);
        // This one points to different slug, should not be updated
        SlugRedirectModel::query()->create([
            'id' => '33333333-3333-3333-3333-333333333333',
            'old_slug' => 'slug-y',
            'new_slug' => 'slug-z',
            'entity_type' => 'article',
            'entity_id' => 'eeeeeeee-2222-2222-2222-222222222222',
        ]);
        // Same old target but different entity type, should not be updated
        SlugRedirectModel::query()->create([
            'id' => '44444444-4444-4444-4444-444444444444',
            'old_slug' => 'slug-m',
            'new_slug' => 'slug-b',
            'entity_type' => 'event',
            'entity_id' => 'eeeeeeee-3333-3333-3333-333333333333',
        ]);

        $this->repository->updateAllPointingTo(
            new Slug('slug-b'),
            new Slug('slug-c'),
            'article'
        );

        // These should be updated
        $this->assertDatabaseHas('slug_redirects', [
            'id' => '11111111-1111-1111-1111-111111111111',
            'new_slug' => 'slug-c',
        ]);
        $this->assertDatabaseHas('slug_redirects', [
            'id' => '22222222-2222-2222-2222-222222222222',
            'new_slug' => 'slug-c',
        ]);

        // These should remain unchanged
        $this->assertDatabaseHas('slug_redirects', [
            'id' => '33333333-3333-3333-3333-333333333333',
            'new_slug' => 'slug-z',
        ]);
        $this->assertDatabaseHas('slug_redirects', [
            'id' => '44444444-4444-4444-4444-444444444444',
            'new_slug' => 'slug-b',
        ]);
    }

    public function test_delete_by_entity_id_removes_redirects(): void
    {
        SlugRedirectModel::query()->create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'old_slug' => 'old-1',
            'new_slug' => 'new-1',
            'entity_type' => 'article',
            'entity_id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        ]);
        SlugRedirectModel::query()->create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'old_slug' => 'old-2',
            'new_slug' => 'new-2',
            'entity_type' => 'article',
            'entity_id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        ]);
        SlugRedirectModel::query()->create([
            'id' => '33333333-3333-3333-3333-333333333333',
            'old_slug' => 'old-3',
            'new_slug' => 'new-3',
            'entity_type' => 'article',
            'entity_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);
        SlugRedirectModel::query()->create([
            'id' => '44444444-4444-4444-4444-444444444444',
            'old_slug' => 'old-4',
            'new_slug' => 'new-4',
            'entity_type' => 'event',
            'entity_id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        ]);

        $this->repository->deleteByEntityId('dddddddd-dddd-dddd-dddd-dddddddddddd', 'article');

        $this->assertDatabaseMissing('slug_redirects', ['id' => '11111111-1111-1111-1111-111111111111']);
        $this->assertDatabaseMissing('slug_redirects', ['id' => '22222222-2222-2222-2222-222222222222']);
        $this->assertDatabaseHas('slug_redirects', ['id' => '33333333-3333-3333-3333-333333333333']);
        $this->assertDatabaseHas('slug_redirects', ['id' => '44444444-4444-4444-4444-444444444444']);
    }
}
