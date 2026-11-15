<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ProfileBioText;
use App\Domain\Profiles\ProfileHandleChangeRule;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;

/**
 * What the owner's editor starts from (core#743): only ever built for the owner of the profile.
 */
final readonly class ProfileEditorDTO
{
    /** Largest banner accepted, in kilobytes (5 MB) */
    public const int BANNER_MAX_KILOBYTES = 5120;

    /** Banner types accepted, as file extensions */
    public const array BANNER_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @param  string|null  $nextHandleChangeAt  when the address may be chosen again, ISO 8601 in the display zone; null when it may now
     * @param  bool  $canShowMembership  whether the owner is an association member, so the badge can be offered
     * @param  list<array{option: ProfileLayoutOptionDTO, enabled: bool}>  $sections  in the owner's order
     * @param  list<array{option: ProfileLayoutOptionDTO, enabled: bool}>  $stats  in the owner's order
     */
    public function __construct(
        public string $handle,
        public ProfileVisibility $visibility,
        public ?string $bio,
        public ?string $bannerPublicId,
        public bool $showMembership,
        public bool $canShowMembership,
        public ?string $nextHandleChangeAt,
        public array $sections,
        public array $stats,
    ) {}

    /**
     * What `@/types/profileEditor` calls `ProfileEditor`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'visibility' => $this->visibility->value,
            'bio' => $this->bio ?? '',
            'bannerPublicId' => $this->bannerPublicId,
            'showMembership' => $this->showMembership && $this->canShowMembership,
            'canShowMembership' => $this->canShowMembership,
            'nextHandleChangeAt' => $this->nextHandleChangeAt,
            'sections' => array_map(static fn (array $entry): array => [
                'key' => $entry['option']->key,
                'label' => $entry['option']->label,
                'description' => $entry['option']->description,
                'enabled' => $entry['enabled'],
                'region' => $entry['option']->region?->value,
                'module' => $entry['option']->module,
                'component' => $entry['option']->component,
            ], $this->sections),
            'stats' => array_map(static fn (array $entry): array => [
                'key' => $entry['option']->key,
                'label' => $entry['option']->label,
                'description' => $entry['option']->description,
                'enabled' => $entry['enabled'],
            ], $this->stats),
            'limits' => [
                'bio' => ProfileBioText::MAX_LENGTH,
                'handleMin' => ProfileHandle::MIN_LENGTH,
                'handleMax' => ProfileHandle::MAX_LENGTH,
                'handleChangeDays' => ProfileHandleChangeRule::INTERVAL_DAYS,
                'bannerKilobytes' => self::BANNER_MAX_KILOBYTES,
                'bannerTypes' => self::BANNER_TYPES,
            ],
        ];
    }
}
