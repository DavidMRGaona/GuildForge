<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\MediaFileStatusRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MediaFileStatusRuleTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-10 12:00:00 UTC');
    }

    public function test_any_usage_means_in_use_even_if_unclaimed(): void
    {
        $this->assertSame(
            MediaFileStatus::InUse,
            MediaFileStatusRule::for(true, false, new DateTimeImmutable('2020-01-01 UTC'), $this->now()),
        );
    }

    public function test_unclaimed_without_usages_is_unknown_origin(): void
    {
        $this->assertSame(
            MediaFileStatus::UnknownOrigin,
            MediaFileStatusRule::for(false, false, new DateTimeImmutable('2020-01-01 UTC'), $this->now()),
        );
    }

    public function test_unclaimed_recent_file_is_unknown_origin(): void
    {
        $this->assertSame(
            MediaFileStatus::UnknownOrigin,
            MediaFileStatusRule::for(false, false, $this->now()->modify('-1 day'), $this->now()),
        );
    }

    public function test_file_within_seven_days_is_recent(): void
    {
        $this->assertSame(
            MediaFileStatus::Recent,
            MediaFileStatusRule::for(false, true, $this->now()->modify('-6 days -23 hours'), $this->now()),
        );
    }

    public function test_file_exactly_seven_days_old_is_unused(): void
    {
        $this->assertSame(
            MediaFileStatus::Unused,
            MediaFileStatusRule::for(false, true, $this->now()->modify('-7 days'), $this->now()),
        );
    }

    public function test_only_unused_can_be_deleted(): void
    {
        $this->assertTrue(MediaFileStatus::Unused->canBeDeleted());
        $this->assertFalse(MediaFileStatus::InUse->canBeDeleted());
        $this->assertFalse(MediaFileStatus::Recent->canBeDeleted());
        $this->assertFalse(MediaFileStatus::UnknownOrigin->canBeDeleted());
    }
}
