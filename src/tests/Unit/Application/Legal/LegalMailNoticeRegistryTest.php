<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Legal;

use App\Application\Legal\DTOs\LegalMailNoticeDTO;
use App\Infrastructure\Legal\LegalMailNoticeRegistry;
use PHPUnit\Framework\TestCase;

final class LegalMailNoticeRegistryTest extends TestCase
{
    public function test_register_and_find(): void
    {
        $registry = new LegalMailNoticeRegistry;
        $notice = $this->notice('mod.first');

        $registry->register($notice);

        $this->assertSame($notice, $registry->find('mod.first'));
        $this->assertNull($registry->find('mod.other'));
        $this->assertSame([$notice], $registry->all());
    }

    public function test_a_repeated_key_replaces_the_notice(): void
    {
        $registry = new LegalMailNoticeRegistry;
        $registry->register($this->notice('mod.first', 'Old'));
        $registry->register($this->notice('mod.second'));
        $registry->register($this->notice('mod.first', 'New'));

        $this->assertCount(2, $registry->all());
        $this->assertSame('New', $registry->find('mod.first')?->label);
    }

    private function notice(string $key, string $label = 'Label'): LegalMailNoticeDTO
    {
        return new LegalMailNoticeDTO(
            key: $key,
            module: 'mod',
            label: $label,
            help: 'Help',
            defaultContent: 'Default',
            placeholders: [':site'],
            preview: static fn (string $content): string => '<p>'.$content.'</p>',
        );
    }
}
