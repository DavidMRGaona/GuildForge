<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Media;

use App\Application\Media\DTOs\MediaUsageDTO;
use PHPUnit\Framework\TestCase;

final class MediaUsageDTOTest extends TestCase
{
    public function test_a_usage_is_shown_unless_its_source_hides_it(): void
    {
        // Sources written before the flag build it with four positional arguments
        $usage = new MediaUsageDTO('events/a.jpg', 'record-1', 'Imagen', 'Torneo de otoño');

        $this->assertSame('events/a.jpg', $usage->storedPath);
        $this->assertSame('record-1', $usage->recordId);
        $this->assertSame('Imagen', $usage->role);
        $this->assertSame('Torneo de otoño', $usage->title);
        $this->assertFalse($usage->hidden);
    }

    public function test_a_source_may_hide_one_usage(): void
    {
        $this->assertTrue((new MediaUsageDTO('badges/a.png', 'record-1', 'Insignia', 'Logro secreto', hidden: true))->hidden);
        $this->assertTrue((new MediaUsageDTO('badges/a.png', 'record-1', 'Insignia', 'Logro secreto', true))->hidden);
    }
}
