<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use Inertia\Inertia;
use Inertia\Response;

final class LegalPageController extends Controller
{
    private const array LEGAL_PAGES = [
        'politica-de-privacidad' => ['prefix' => 'legal_privacy_', 'title_key' => 'legal.privacy_policy'],
        'aviso-legal' => ['prefix' => 'legal_notice_', 'title_key' => 'legal.legal_notice'],
        'politica-de-cookies' => ['prefix' => 'legal_cookies_', 'title_key' => 'legal.cookie_policy'],
        'terminos-y-condiciones' => ['prefix' => 'legal_terms_', 'title_key' => 'legal.terms_and_conditions'],
    ];

    public function __construct(
        private readonly SettingsServiceInterface $settings,
    ) {
    }

    public function show(string $slug): Response
    {
        $page = self::LEGAL_PAGES[$slug] ?? null;

        if ($page === null) {
            abort(404);
        }

        $prefix = $page['prefix'];
        $published = (string) $this->settings->get($prefix . 'published', '');

        if ($published !== '1' && $published !== 'true') {
            abort(404);
        }

        $content = (string) $this->settings->get($prefix . 'content', '');
        $content = $this->replacePlaceholders($content);

        return Inertia::render('Legal/Show', [
            'title' => __($page['title_key']),
            'content' => $content,
            'lastUpdated' => null,
        ]);
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
