<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaUrlExtractorInterface;
use App\Domain\Footer\FooterLogos;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Pages\Settings\FooterSettings;
use App\Infrastructure\Content\Sources\FooterTextSource;
use App\Infrastructure\Footer\StoredFooterLayout;

/**
 * The images of the configurable footer (core#698), all in one settings row (`footer_layout`), so the
 * only record is `layout`. It claims `footer/` (trailing slash), which covers `footer/logos/` and the
 * editor images of the text blocks in `footer/content/`, and counts as uses:
 *
 * - the logos of every `core.logos` block (stored paths), and
 * - every delivery URL inside the text of a `core.text` block, wherever the file lives: a file of
 *   another folder that is only linked from the footer text is in use, not «unused».
 *
 * Read from the table, never the settings cache; an unreadable row throws, so no file looks unused.
 */
final readonly class FooterMediaSource implements MediaUsageSourceInterface, ReleasesMediaThroughCoreInterface, ShowsMediaPreviews
{
    public const string TYPE = 'footer';

    public const string RECORD_ID = 'layout';

    public function __construct(
        private StoredFooterLayout $stored,
        private MediaFileIdResolverInterface $resolver,
        private MediaUrlExtractorInterface $urls,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('filament.media_library.sources.footer');
    }

    public function folders(): array
    {
        return ['footer/'];
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
        foreach ($this->stored->blocks() as $block) {
            if ($block['key'] === FooterLogos::BLOCK_KEY) {
                foreach (FooterLogos::itemsIn($block['settings']) as $logo) {
                    yield new MediaUsageDTO(
                        storedPath: $logo['image'],
                        recordId: self::RECORD_ID,
                        role: __('filament.media_library.roles.footer_logo'),
                        title: __('filament.media_library.footer_titles.logo', ['alt' => $logo['alt'] !== '' ? $logo['alt'] : $logo['image']]),
                    );
                }
            }

            if ($block['key'] === FooterTextSource::BLOCK_KEY) {
                foreach ($this->urls->fileIdsIn($block['settings'][FooterTextSource::CONTENT_FIELD] ?? null) as $url) {
                    yield new MediaUsageDTO(
                        storedPath: $url,
                        recordId: self::RECORD_ID,
                        role: __('filament.media_library.roles.in_text'),
                        title: __('filament.media_library.footer_titles.text', ['column' => $block['column'] + 1]),
                    );
                }
            }
        }
    }

    /**
     * @return list<MediaUsageDTO>
     */
    public function usagesFor(string $recordId): array
    {
        return $recordId === self::RECORD_ID ? array_values([...$this->usages()]) : [];
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
        return $recordId === self::RECORD_ID && $this->authorization->can($user, FooterSettings::PERMISSION)
            ? FooterSettings::getUrl()
            : null;
    }
}
