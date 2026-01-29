<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\DTOs\Response\LegalPageResponseDTO;
use App\Application\Services\LegalPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Services\LegalPageService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(LegalPageService::class)]
final class LegalPageServiceTest extends TestCase
{
    private MockObject&SettingsServiceInterface $settings;

    private LegalPageServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = $this->createMock(SettingsServiceInterface::class);
        $this->service = new LegalPageService($this->settings);
    }

    #[Test]
    #[DataProvider('validSlugsProvider')]
    public function it_validates_known_slugs(string $slug): void
    {
        $this->assertTrue($this->service->isValidSlug($slug));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validSlugsProvider(): array
    {
        return [
            'privacy policy' => ['politica-de-privacidad'],
            'legal notice' => ['aviso-legal'],
            'cookie policy' => ['politica-de-cookies'],
            'terms and conditions' => ['terminos-y-condiciones'],
        ];
    }

    #[Test]
    public function it_rejects_unknown_slugs(): void
    {
        $this->assertFalse($this->service->isValidSlug('unknown-slug'));
        $this->assertFalse($this->service->isValidSlug(''));
    }

    #[Test]
    public function it_returns_null_for_unpublished_page(): void
    {
        $this->settings->method('get')
            ->willReturnCallback(function (string $key, mixed $default = null): mixed {
                if ($key === 'legal_privacy_published') {
                    return '0';
                }

                return $default;
            });

        $result = $this->service->getPublishedPage('politica-de-privacidad');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_unknown_slug(): void
    {
        $result = $this->service->getPublishedPage('unknown-slug');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_dto_for_published_page(): void
    {
        $this->settings->method('get')
            ->willReturnCallback(function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'legal_privacy_published' => '1',
                    'legal_privacy_content' => '<p>Contenido de privacidad</p>',
                    default => $default,
                };
            });

        $result = $this->service->getPublishedPage('politica-de-privacidad');

        $this->assertInstanceOf(LegalPageResponseDTO::class, $result);
        $this->assertSame('<p>Contenido de privacidad</p>', $result->content);
        $this->assertNull($result->lastUpdated);
    }

    #[Test]
    public function it_replaces_placeholders_in_content(): void
    {
        $contentWithPlaceholders = '<p>Nombre: {{nombre}}, CIF: {{cif}}, Email: {{email}}</p>';

        $this->settings->method('get')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($contentWithPlaceholders): mixed {
                return match ($key) {
                    'legal_notice_published' => 'true',
                    'legal_notice_content' => $contentWithPlaceholders,
                    'legal_entity_name' => 'Mi Asociación',
                    'legal_entity_cif' => 'G12345678',
                    'legal_entity_email' => 'info@example.com',
                    default => $default,
                };
            });

        $result = $this->service->getPublishedPage('aviso-legal');

        $this->assertInstanceOf(LegalPageResponseDTO::class, $result);
        $this->assertSame('<p>Nombre: Mi Asociación, CIF: G12345678, Email: info@example.com</p>', $result->content);
    }

    #[Test]
    public function it_replaces_all_placeholders(): void
    {
        $content = '{{nombre}} {{cif}} {{direccion}} {{email}} {{telefono}} {{email_dpo}} {{registro}} {{dominio}}';

        $this->settings->method('get')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($content): mixed {
                return match ($key) {
                    'legal_cookies_published' => '1',
                    'legal_cookies_content' => $content,
                    'legal_entity_name' => 'Nombre',
                    'legal_entity_cif' => 'CIF',
                    'legal_entity_address' => 'Dirección',
                    'legal_entity_email' => 'Email',
                    'legal_entity_phone' => 'Teléfono',
                    'legal_entity_dpo_email' => 'DPO',
                    'legal_entity_registry' => 'Registro',
                    'legal_entity_domain' => 'Dominio',
                    default => $default,
                };
            });

        $result = $this->service->getPublishedPage('politica-de-cookies');

        $this->assertInstanceOf(LegalPageResponseDTO::class, $result);
        $this->assertSame('Nombre CIF Dirección Email Teléfono DPO Registro Dominio', $result->content);
    }

    #[Test]
    public function it_accepts_true_string_as_published(): void
    {
        $this->settings->method('get')
            ->willReturnCallback(function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'legal_terms_published' => 'true',
                    'legal_terms_content' => 'Terms content',
                    default => $default,
                };
            });

        $result = $this->service->getPublishedPage('terminos-y-condiciones');

        $this->assertNotNull($result);
    }
}
