<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal;

use App\Application\Legal\DTOs\LegalEntityDTO;
use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
use App\Application\Legal\Services\LegalMailNoticeServiceInterface;
use App\Application\Services\SettingsServiceInterface;

final readonly class LegalMailNoticeService implements LegalMailNoticeServiceInterface
{
    public function __construct(
        private LegalMailNoticeRegistryInterface $registry,
        private SettingsServiceInterface $settings,
    ) {}

    public function content(string $key): string
    {
        $notice = $this->registry->find($key);

        if ($notice === null) {
            return '';
        }

        $saved = $this->settings->get('legal_mail_notice:'.$key);

        if (is_string($saved) && trim($saved) !== '') {
            return $saved;
        }

        return $notice->defaultContent;
    }

    public function paragraphs(string $key, array $replacements): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $this->content($key));
        $paragraphs = [];

        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph !== '') {
                $paragraphs[] = strtr($paragraph, $replacements);
            }
        }

        return $paragraphs;
    }

    public function entity(): LegalEntityDTO
    {
        return new LegalEntityDTO(
            name: $this->setting('legal_entity_name') ?? (string) config('app.name', 'GuildForge'),
            email: $this->setting('legal_entity_email'),
            dpoEmail: $this->setting('legal_entity_dpo_email'),
        );
    }

    private function setting(string $key): ?string
    {
        $value = $this->settings->get($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
