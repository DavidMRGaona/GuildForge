<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\SiteSettings;

/**
 * Images kept in site settings (logos, favicons, the «Nosotros» hero); the record id is the settings key.
 */
final readonly class SiteSettingsMediaSource implements MediaUsageSourceInterface, ReleasesMediaThroughCoreInterface
{
    /** Settings keys edited in «Configuración del sitio» */
    private const array SITE_KEYS = [
        'site_logo_light',
        'site_logo_dark',
        'site_logo_email',
        'site_favicon_light',
        'site_favicon_dark',
    ];

    private const string ABOUT_KEY = 'about_hero_image';

    public function __construct(
        private SettingsServiceInterface $settings,
        private MediaFileIdResolverInterface $resolver,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function type(): string
    {
        return 'settings';
    }

    public function label(): string
    {
        return __('filament.media_library.sources.settings');
    }

    public function folders(): array
    {
        return ['branding/', 'about/'];
    }

    public function excludedSubfolders(): array
    {
        return [];
    }

    public function hidden(): bool
    {
        return false;
    }

    /**
     * @return iterable<MediaUsageDTO>
     */
    public function usages(): iterable
    {
        foreach ($this->keys() as $key) {
            $usage = $this->usageOf($key);

            if ($usage instanceof MediaUsageDTO) {
                yield $usage;
            }
        }
    }

    /**
     * @return list<MediaUsageDTO>
     */
    public function usagesFor(string $recordId): array
    {
        $usage = in_array($recordId, $this->keys(), true) ? $this->usageOf($recordId) : null;

        return $usage instanceof MediaUsageDTO ? [$usage] : [];
    }

    public function usesFile(MediaFileId $file): bool
    {
        foreach ($this->usages() as $usage) {
            $id = $this->resolver->fromStoredPath($usage->storedPath);

            if ($id instanceof MediaFileId && $id->equals($file)) {
                return true;
            }
        }

        return false;
    }

    public function editUrl(string $recordId, object $user): ?string
    {
        if ($recordId === self::ABOUT_KEY) {
            return $this->authorization->can($user, 'pages.manage') ? AboutPageSettings::getUrl() : null;
        }

        if (in_array($recordId, self::SITE_KEYS, true) && $this->authorization->can($user, 'settings.manage')) {
            return SiteSettings::getUrl();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        return [...self::SITE_KEYS, self::ABOUT_KEY];
    }

    private function usageOf(string $key): ?MediaUsageDTO
    {
        $value = $this->settings->get($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $field = __('filament.media_library.roles.'.$key);

        // Several fields often hold copies of one logo: the field tells their cards apart
        return new MediaUsageDTO(
            storedPath: $value,
            recordId: $key,
            role: $field,
            title: $field,
        );
    }
}
