<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\LegalPageResponseDTO;
use App\Application\Services\LegalPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Support\SanitizesHtml;

final readonly class LegalPageService implements LegalPageServiceInterface
{
    use SanitizesHtml;

    /**
     * @var array<string, array{prefix: string, title_key: string}>
     */
    private const array LEGAL_PAGES = [
        'politica-de-privacidad' => ['prefix' => 'legal_privacy_', 'title_key' => 'legal.privacy_policy'],
        'aviso-legal' => ['prefix' => 'legal_notice_', 'title_key' => 'legal.legal_notice'],
        'politica-de-cookies' => ['prefix' => 'legal_cookies_', 'title_key' => 'legal.cookie_policy'],
        'terminos-y-condiciones' => ['prefix' => 'legal_terms_', 'title_key' => 'legal.terms_and_conditions'],
    ];

    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    public function getPublishedPage(string $slug): ?LegalPageResponseDTO
    {
        $page = self::LEGAL_PAGES[$slug] ?? null;

        if ($page === null) {
            return null;
        }

        $prefix = $page['prefix'];
        $published = (string) $this->settings->get($prefix.'published', '');

        if (! $this->isPagePublished($published)) {
            return null;
        }

        $content = (string) $this->settings->get($prefix.'content', '');
        $content = $this->replacePlaceholders($content);
        $content = $this->sanitizeHtml($content);

        return new LegalPageResponseDTO(
            title: __($page['title_key']),
            content: $content,
            lastUpdated: null,
        );
    }

    public function isValidSlug(string $slug): bool
    {
        return isset(self::LEGAL_PAGES[$slug]);
    }

    private function isPagePublished(string $value): bool
    {
        return $value === '1' || $value === 'true';
    }

    private function replacePlaceholders(string $content): string
    {
        $placeholders = [
            '{{nombre}}' => (string) $this->settings->get('legal_entity_name', ''),
            '{{cif}}' => (string) $this->settings->get('legal_entity_cif', ''),
            '{{direccion}}' => (string) $this->settings->get('legal_entity_address', ''),
            '{{email}}' => (string) $this->settings->get('legal_entity_email', ''),
            '{{telefono}}' => (string) $this->settings->get('legal_entity_phone', ''),
            '{{email_dpo}}' => (string) $this->settings->get('legal_entity_dpo_email', ''),
            '{{registro}}' => (string) $this->settings->get('legal_entity_registry', ''),
            '{{dominio}}' => (string) $this->settings->get('legal_entity_domain', ''),
        ];

        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $content,
        );
    }
}
