<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Domain\Media\ValueObjects\MediaFileId;

class FakeMediaSource implements MediaUsageSourceInterface
{
    /**
     * @param  list<string>  $folders
     * @param  list<string>  $excludedSubfolders
     */
    public function __construct(
        private readonly string $type = 'fake',
        private readonly array $folders = ['fake/'],
        private readonly bool $hidden = false,
        private readonly array $excludedSubfolders = [],
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return 'Fakes';
    }

    public function folders(): array
    {
        return $this->folders;
    }

    public function excludedSubfolders(): array
    {
        return $this->excludedSubfolders;
    }

    public function hidden(): bool
    {
        return $this->hidden;
    }

    /**
     * @return iterable<MediaUsageDTO>
     */
    public function usages(): iterable
    {
        return [];
    }

    /**
     * @return list<MediaUsageDTO>
     */
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
