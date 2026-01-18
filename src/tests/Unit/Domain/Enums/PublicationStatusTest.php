<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\PublicationStatus;
use PHPUnit\Framework\TestCase;

final class PublicationStatusTest extends TestCase
{
    public function test_it_has_draft_status(): void
    {
        $this->assertContains(
            PublicationStatus::Draft,
            PublicationStatus::cases()
        );
    }

    public function test_it_has_published_status(): void
    {
        $this->assertContains(
            PublicationStatus::Published,
            PublicationStatus::cases()
        );
    }

    public function test_draft_has_correct_value(): void
    {
        $this->assertEquals('draft', PublicationStatus::Draft->value);
    }

    public function test_published_has_correct_value(): void
    {
        $this->assertEquals('published', PublicationStatus::Published->value);
    }

    public function test_it_has_exactly_two_cases(): void
    {
        $this->assertCount(2, PublicationStatus::cases());
    }
}
