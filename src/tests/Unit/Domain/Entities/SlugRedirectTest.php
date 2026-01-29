<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\SlugRedirect;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SlugRedirectTest extends TestCase
{
    public function test_it_creates_slug_redirect(): void
    {
        $id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $oldSlug = new Slug('old-slug');
        $newSlug = new Slug('new-slug');
        $entityType = 'article';
        $entityId = 'b2c3d4e5-f6a7-8901-bcde-f23456789012';
        $createdAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $redirect = new SlugRedirect(
            id: $id,
            oldSlug: $oldSlug,
            newSlug: $newSlug,
            entityType: $entityType,
            entityId: $entityId,
            createdAt: $createdAt,
        );

        $this->assertEquals($id, $redirect->id());
        $this->assertEquals($oldSlug, $redirect->oldSlug());
        $this->assertEquals($newSlug, $redirect->newSlug());
        $this->assertEquals($entityType, $redirect->entityType());
        $this->assertEquals($entityId, $redirect->entityId());
        $this->assertEquals($createdAt, $redirect->createdAt());
    }

    public function test_points_to_same_slug_returns_true_when_equal(): void
    {
        $redirect = new SlugRedirect(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            oldSlug: new Slug('same-slug'),
            newSlug: new Slug('same-slug'),
            entityType: 'article',
            entityId: 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
            createdAt: new DateTimeImmutable(),
        );

        $this->assertTrue($redirect->pointsToSameSlug());
    }

    public function test_points_to_same_slug_returns_false_when_different(): void
    {
        $redirect = new SlugRedirect(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            oldSlug: new Slug('old-slug'),
            newSlug: new Slug('new-slug'),
            entityType: 'article',
            entityId: 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
            createdAt: new DateTimeImmutable(),
        );

        $this->assertFalse($redirect->pointsToSameSlug());
    }

    public function test_update_target_returns_new_instance_with_updated_slug(): void
    {
        $originalCreatedAt = new DateTimeImmutable('2024-01-15 10:00:00');
        $redirect = new SlugRedirect(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            oldSlug: new Slug('old-slug'),
            newSlug: new Slug('intermediate-slug'),
            entityType: 'article',
            entityId: 'b2c3d4e5-f6a7-8901-bcde-f23456789012',
            createdAt: $originalCreatedAt,
        );

        $newTargetSlug = new Slug('final-slug');
        $updatedRedirect = $redirect->updateTarget($newTargetSlug);

        // Original unchanged
        $this->assertEquals('intermediate-slug', $redirect->newSlug()->value);

        // New instance has updated slug
        $this->assertNotSame($redirect, $updatedRedirect);
        $this->assertEquals($redirect->id(), $updatedRedirect->id());
        $this->assertEquals($redirect->oldSlug(), $updatedRedirect->oldSlug());
        $this->assertEquals($newTargetSlug, $updatedRedirect->newSlug());
        $this->assertEquals($redirect->entityType(), $updatedRedirect->entityType());
        $this->assertEquals($redirect->entityId(), $updatedRedirect->entityId());
        $this->assertEquals($originalCreatedAt, $updatedRedirect->createdAt());
    }
}
