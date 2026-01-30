<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LegalPageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    #[DataProvider('legalPagesProvider')]
    public function it_shows_published_legal_page(string $slug, string $titleKey): void
    {
        $settings = app(SettingsServiceInterface::class);
        $prefix = $this->getPrefixForSlug($slug);

        $settings->set($prefix.'published', '1');
        $settings->set($prefix.'content', '<p>Test legal content</p>');

        $response = $this->get(route('legal.show', ['slug' => $slug]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
            ->component('Legal/Show')
            ->has('title')
            ->where('content', '<p>Test legal content</p>')
        );
    }

    #[Test]
    #[DataProvider('legalPagesProvider')]
    public function it_returns_404_for_unpublished_legal_page(string $slug, string $titleKey): void
    {
        $settings = app(SettingsServiceInterface::class);
        $prefix = $this->getPrefixForSlug($slug);

        $settings->set($prefix.'published', '0');
        $settings->set($prefix.'content', '<p>Test legal content</p>');

        $response = $this->get(route('legal.show', ['slug' => $slug]));

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_for_unknown_slug(): void
    {
        $response = $this->get(route('legal.show', ['slug' => 'unknown-legal-page']));

        $response->assertStatus(404);
    }

    #[Test]
    public function it_replaces_placeholders_in_content(): void
    {
        $settings = app(SettingsServiceInterface::class);

        $settings->set('legal_privacy_published', '1');
        $settings->set('legal_privacy_content', 'Entity: {{nombre}}, CIF: {{cif}}');
        $settings->set('legal_entity_name', 'Test Association');
        $settings->set('legal_entity_cif', 'G12345678');

        $response = $this->get(route('legal.show', ['slug' => 'politica-de-privacidad']));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
            ->component('Legal/Show')
            ->where('content', 'Entity: Test Association, CIF: G12345678')
        );
    }

    #[Test]
    public function it_accepts_true_string_as_published(): void
    {
        $settings = app(SettingsServiceInterface::class);

        $settings->set('legal_notice_published', 'true');
        $settings->set('legal_notice_content', '<p>Legal notice content</p>');

        $response = $this->get(route('legal.show', ['slug' => 'aviso-legal']));

        $response->assertStatus(200);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function legalPagesProvider(): array
    {
        return [
            'privacy policy' => ['politica-de-privacidad', 'legal.privacy_policy'],
            'legal notice' => ['aviso-legal', 'legal.legal_notice'],
            'cookie policy' => ['politica-de-cookies', 'legal.cookie_policy'],
            'terms and conditions' => ['terminos-y-condiciones', 'legal.terms_and_conditions'],
        ];
    }

    private function getPrefixForSlug(string $slug): string
    {
        return match ($slug) {
            'politica-de-privacidad' => 'legal_privacy_',
            'aviso-legal' => 'legal_notice_',
            'politica-de-cookies' => 'legal_cookies_',
            'terminos-y-condiciones' => 'legal_terms_',
            default => throw new \InvalidArgumentException("Unknown slug: $slug"),
        };
    }
}
