<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Domain\Media\ValueObjects\MediaFileId;
use RuntimeException;

/**
 * Media source whose usages are set by the test; it can fail after yielding some of them.
 */
final class InMemoryMediaSource implements MediaUsageSourceInterface
{
    /** @var list<MediaUsageDTO> */
    public array $usages = [];

    /** usages() throws after yielding what it has */
    public bool $failing = false;

    /**
     * @param  list<string>  $folders
     */
    public function __construct(
        private readonly string $type = 'memory',
        private readonly array $folders = ['memory/'],
    ) {}

    public function use(string $storedPath, string $recordId, string $role = 'Imagen', string $title = 'Registro'): self
    {
        $this->usages[] = new MediaUsageDTO($storedPath, $recordId, $role, $title);

        return $this;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return 'Registros';
    }

    public function folders(): array
    {
        return $this->folders;
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
        yield from $this->usages;

        if ($this->failing) {
            throw new RuntimeException('source failed');
        }
    }

    /**
     * @return list<MediaUsageDTO>
     */
    public function usagesFor(string $recordId): array
    {
        return array_values(array_filter(
            $this->usages,
            static fn (MediaUsageDTO $usage): bool => $usage->recordId === $recordId,
        ));
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
