<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Application\Media\Contracts\ConcealsUnusedMediaFiles;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Media\ValueObjects\MediaFileId;

/**
 * The editor images of the articles' texts (`articles/content/`, core#838). The web editor stores here too, where
 * members write the articles they propose (core#573, core#579), so an image left unused may come from a draft
 * only its author saw: never sent, or taken out of the text before sending it. Like the images of the web proposal
 * forms (ProposalImageMediaSource, core#841), the unused ones are concealed; the used ones show, and both are
 * deleted alike.
 *
 * It gives no usages of its own: RichTextMediaSource reads every article text (drafts included, their usages
 * hidden) and reports the files they name, whatever folder they are in.
 */
final readonly class ArticleTextImageMediaSource implements ConcealsUnusedMediaFiles, MediaUsageSourceInterface, ReleasesMediaThroughCoreInterface, ShowsMediaPreviews
{
    public const string TYPE = 'core.article-text-images';

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('filament.media_library.sources.article_text_images');
    }

    public function folders(): array
    {
        return [EditorImageOrigin::Articles->folder()->claim()];
    }

    public function excludedSubfolders(): array
    {
        return [];
    }

    public function hidden(): bool
    {
        return false;
    }

    public function usages(): iterable
    {
        return [];
    }

    public function usagesFor(string $recordId): array
    {
        return [];
    }

    public function usesFile(MediaFileId $file): bool
    {
        return false;
    }

    public function editUrl(string $recordId, object $user): ?string
    {
        return null;
    }
}
