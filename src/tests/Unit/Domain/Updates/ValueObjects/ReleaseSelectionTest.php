<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Updates\ValueObjects;

use App\Domain\Updates\ValueObjects\ReleaseSelection;
use PHPUnit\Framework\TestCase;

final class ReleaseSelectionTest extends TestCase
{
    public function test_none_selects_nothing(): void
    {
        $none = ReleaseSelection::none();

        $this->assertNull($none->compatible);
        $this->assertNull($none->compatibleCoreConstraint);
        $this->assertNull($none->blocked);
        $this->assertNull($none->blockedCoreConstraint);
        $this->assertSame([], $none->blockedIssues);
    }
}
