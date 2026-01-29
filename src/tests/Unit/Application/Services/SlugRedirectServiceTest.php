<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Services\SlugRedirectService;
use App\Domain\Entities\SlugRedirect;
use App\Domain\Repositories\SlugRedirectRepositoryInterface;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

final class SlugRedirectServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_current_slug_returns_new_slug_when_redirect_exists(): void
    {
        $oldSlug = 'old-article-slug';
        $newSlug = 'new-article-slug';
        $entityType = 'article';

        $redirect = new SlugRedirect(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            oldSlug: new Slug($oldSlug),
            newSlug: new Slug($newSlug),
            entityType: $entityType,
            entityId: 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
            createdAt: new DateTimeImmutable(),
        );

        $repository = Mockery::mock(SlugRedirectRepositoryInterface::class);
        $repository->shouldReceive('findByOldSlugAndType')
            ->once()
            ->with(Mockery::on(fn (Slug $slug) => $slug->value === $oldSlug), $entityType)
            ->andReturn($redirect);

        $service = new SlugRedirectService($repository);

        $result = $service->resolveCurrentSlug($oldSlug, $entityType);

        $this->assertEquals($newSlug, $result);
    }

    public function test_resolve_current_slug_returns_null_when_no_redirect(): void
    {
        $slug = 'current-slug';
        $entityType = 'article';

        $repository = Mockery::mock(SlugRedirectRepositoryInterface::class);
        $repository->shouldReceive('findByOldSlugAndType')
            ->once()
            ->andReturn(null);

        $service = new SlugRedirectService($repository);

        $result = $service->resolveCurrentSlug($slug, $entityType);

        $this->assertNull($result);
    }

    public function test_handle_slug_change_does_nothing_when_slugs_are_equal(): void
    {
        $slug = 'same-slug';
        $entityType = 'article';
        $entityId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $repository = Mockery::mock(SlugRedirectRepositoryInterface::class);
        $repository->shouldNotReceive('findByOldSlugAndType');
        $repository->shouldNotReceive('save');
        $repository->shouldNotReceive('updateAllPointingTo');

        $service = new SlugRedirectService($repository);

        $service->handleSlugChange($slug, $slug, $entityType, $entityId);

        // If we get here without exceptions, the test passes
        $this->assertTrue(true);
    }

    public function test_handle_slug_change_creates_redirect(): void
    {
        $oldSlug = 'old-slug';
        $newSlug = 'new-slug';
        $entityType = 'article';
        $entityId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $repository = Mockery::mock(SlugRedirectRepositoryInterface::class);
        $repository->shouldReceive('findByOldSlugAndType')
            ->once()
            ->with(Mockery::on(fn (Slug $slug) => $slug->value === $oldSlug), $entityType)
            ->andReturn(null);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (SlugRedirect $redirect) use ($oldSlug, $newSlug, $entityType, $entityId) {
                return $redirect->oldSlug()->value === $oldSlug
                    && $redirect->newSlug()->value === $newSlug
                    && $redirect->entityType() === $entityType
                    && $redirect->entityId() === $entityId;
            }));
        $repository->shouldReceive('updateAllPointingTo')
            ->once()
            ->with(
                Mockery::on(fn (Slug $slug) => $slug->value === $oldSlug),
                Mockery::on(fn (Slug $slug) => $slug->value === $newSlug),
                $entityType
            );

        $service = new SlugRedirectService($repository);

        $service->handleSlugChange($oldSlug, $newSlug, $entityType, $entityId);

        $this->assertTrue(true);
    }

    public function test_handle_slug_change_updates_existing_redirect(): void
    {
        $oldSlug = 'old-slug';
        $newSlug = 'new-slug';
        $entityType = 'article';
        $entityId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $existingRedirect = new SlugRedirect(
            id: 'redirect-id-123',
            oldSlug: new Slug($oldSlug),
            newSlug: new Slug('intermediate-slug'),
            entityType: $entityType,
            entityId: $entityId,
            createdAt: new DateTimeImmutable('2024-01-01'),
        );

        $repository = Mockery::mock(SlugRedirectRepositoryInterface::class);
        $repository->shouldReceive('findByOldSlugAndType')
            ->once()
            ->andReturn($existingRedirect);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (SlugRedirect $redirect) use ($newSlug) {
                return $redirect->id() === 'redirect-id-123'
                    && $redirect->newSlug()->value === $newSlug;
            }));
        $repository->shouldReceive('updateAllPointingTo')
            ->once();

        $service = new SlugRedirectService($repository);

        $service->handleSlugChange($oldSlug, $newSlug, $entityType, $entityId);

        $this->assertTrue(true);
    }

    public function test_handle_slug_change_updates_chain_redirects(): void
    {
        $oldSlug = 'slug-a';
        $newSlug = 'slug-c';
        $entityType = 'article';
        $entityId = 'entity-123';

        $repository = Mockery::mock(SlugRedirectRepositoryInterface::class);
        $repository->shouldReceive('findByOldSlugAndType')
            ->once()
            ->andReturn(null);
        $repository->shouldReceive('save')
            ->once();
        $repository->shouldReceive('updateAllPointingTo')
            ->once()
            ->with(
                Mockery::on(fn (Slug $slug) => $slug->value === $oldSlug),
                Mockery::on(fn (Slug $slug) => $slug->value === $newSlug),
                $entityType
            );

        $service = new SlugRedirectService($repository);

        $service->handleSlugChange($oldSlug, $newSlug, $entityType, $entityId);

        $this->assertTrue(true);
    }
}
