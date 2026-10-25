<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use App\Application\Legal\DTOs\LegalMailNoticeDTO;
use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
use App\Application\Legal\Services\LegalMailNoticeServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class LegalMailNoticeServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(LegalMailNoticeRegistryInterface::class)->register(new LegalMailNoticeDTO(
            key: 'mod.notice',
            module: 'mod',
            label: 'Notice',
            help: 'Help',
            defaultContent: "Default one for :site\n\nDefault two",
            placeholders: [':site'],
            preview: static fn (string $content): string => $content,
        ));
    }

    public function test_content_falls_back_to_the_default(): void
    {
        $service = app(LegalMailNoticeServiceInterface::class);

        $this->assertSame("Default one for :site\n\nDefault two", $service->content('mod.notice'));
        $this->assertSame('', $service->content('mod.unknown'));
    }

    public function test_blank_content_falls_back_to_the_default(): void
    {
        $service = app(LegalMailNoticeServiceInterface::class);

        app(SettingsServiceInterface::class)->set('legal_mail_notice:mod.notice', '   ');
        $this->assertSame("Default one for :site\n\nDefault two", $service->content('mod.notice'));

        app(SettingsServiceInterface::class)->set('legal_mail_notice:mod.notice', 'Custom');
        $this->assertSame('Custom', $service->content('mod.notice'));
    }

    public function test_paragraphs_split_and_fill_placeholders(): void
    {
        app(SettingsServiceInterface::class)->set(
            'legal_mail_notice:mod.notice',
            "  First :inviter  \n\n\n   \nSecond :site and :days\n",
        );

        $paragraphs = app(LegalMailNoticeServiceInterface::class)->paragraphs('mod.notice', [
            ':inviter' => 'John Doe',
            ':site' => 'Sitio de ejemplo',
            ':days' => '14',
        ]);

        $this->assertSame(['First John Doe', 'Second Sitio de ejemplo and 14'], $paragraphs);
    }

    public function test_entity_falls_back_to_the_site_name(): void
    {
        config(['app.name' => 'Sitio de ejemplo']);
        $service = app(LegalMailNoticeServiceInterface::class);

        $entity = $service->entity();
        $this->assertSame('Sitio de ejemplo', $entity->name);
        $this->assertNull($entity->email);
        $this->assertNull($entity->dpoEmail);

        $settings = app(SettingsServiceInterface::class);
        $settings->set('legal_entity_name', 'Asociación de ejemplo');
        $settings->set('legal_entity_email', 'hola@example.org');
        $settings->set('legal_entity_dpo_email', 'dpo@example.org');

        $entity = $service->entity();
        $this->assertSame('Asociación de ejemplo', $entity->name);
        $this->assertSame('hola@example.org', $entity->email);
        $this->assertSame('dpo@example.org', $entity->dpoEmail);
    }
}
