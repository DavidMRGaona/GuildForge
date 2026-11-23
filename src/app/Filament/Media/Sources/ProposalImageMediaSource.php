<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Application\Media\Contracts\ConcealsUnusedMediaFiles;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Domain\Proposals\Enums\ProposalType;

/**
 * The folders of the images uploaded from the web proposal forms (core#841), apart from the panel's ones so
 * their unused files can be concealed without hiding the replaced covers the library is there to review: an
 * image of a refused save, or a draft's image that could not be let go of, may be its author's alone.
 *
 * It gives no usages of its own: the events and articles sources report every record whatever folder its image
 * is in (and hide the ones of unsubmitted drafts), and they leave these folders out of their claim.
 */
final readonly class ProposalImageMediaSource implements ConcealsUnusedMediaFiles, MediaUsageSourceInterface, ReleasesMediaThroughCoreInterface, ShowsMediaPreviews
{
    public const string TYPE = 'core.proposal-images';

    /** Subfolder of each type's folder, with its trailing slash (ProposalType::imageFolder()) */
    public const string SUBFOLDER = ProposalType::IMAGE_SUBFOLDER;

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('filament.media_library.sources.proposal_images');
    }

    public function folders(): array
    {
        return array_map(static fn (ProposalType $type): string => $type->imageFolder(), ProposalType::cases());
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
