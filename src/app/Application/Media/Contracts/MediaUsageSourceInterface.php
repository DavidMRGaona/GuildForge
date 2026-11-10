<?php

declare(strict_types=1);

namespace App\Application\Media\Contracts;

use App\Application\Media\DTOs\MediaUsageDTO;
use App\Domain\Media\ValueObjects\MediaFileId;

/**
 * Something that stores files in the shared media storage and knows which records use them.
 * Modules prefix their type with the module name (e.g. `gametables.tables`).
 */
interface MediaUsageSourceInterface
{
    /**
     * Stable, unique identifier of the source.
     */
    public function type(): string;

    /**
     * Name of the origin in plural, shown to people (e.g. «Eventos»).
     */
    public function label(): string;

    /**
     * Folders this source claims, each with a trailing slash (e.g. `events/`).
     *
     * @return list<string>
     */
    public function folders(): array;

    /**
     * Subfolders of the claimed folders that this source does not claim, relative to each claimed folder
     * and with a trailing slash (e.g. `content/`), so another source can claim them.
     *
     * @return list<string>
     */
    public function excludedSubfolders(): array;

    /**
     * True only for sources whose files are not listed in the library (avatars).
     */
    public function hidden(): bool;

    /**
     * Every use of a file by this source.
     *
     * @return iterable<MediaUsageDTO>
     */
    public function usages(): iterable;

    /**
     * Uses of files by one record; empty when the record does not exist.
     *
     * @return list<MediaUsageDTO>
     */
    public function usagesFor(string $recordId): array;

    /**
     * Whether any record of this source uses the file right now.
     */
    public function usesFile(MediaFileId $file): bool;

    /**
     * Where the person can edit the record, or null when they cannot.
     */
    public function editUrl(string $recordId, object $user): ?string;
}
