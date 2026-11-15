<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileHeaderDTO;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use App\Application\Profiles\DTOs\ResolvedProfileSectionDTO;
use App\Application\Profiles\DTOs\ResolvedProfileStatDTO;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\SiteBlocks\Exceptions\NothingToDrawException;
use App\Domain\Profiles\ProfileRegion;
use App\Domain\Profiles\ValueObjects\ProfileKey;
use App\Domain\Profiles\ValueObjects\ProfileLayout;
use App\Domain\Profiles\ValueObjects\ProfileLayoutEntry;
use App\Domain\SiteBlocks\LinkTargetRule;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use JsonException;
use Throwable;

/**
 * Draws a public profile for one person and fails closed (see the interface). A profile is drawn on
 * every visit, so a section or stat that keeps failing would log on every request: each key and
 * reason is warned about once a day (DailyWarnings). Warnings carry the key, the reason and an
 * exception only by its class; never props, counts or exception messages.
 */
final readonly class ProfileRenderer implements ProfileRendererInterface
{
    private DailyWarnings $warnings;

    public function __construct(
        private ProfileSectionRegistryInterface $sections,
        private ProfileStatRegistryInterface $stats,
        private StoredProfiles $profiles,
        private AssociationMembershipServiceInterface $membership,
        private TimezoneServiceInterface $timezone,
        Cache $cache,
    ) {
        $this->warnings = new DailyWarnings(ProfileContributionRegistry::WARNING_PREFIX, static fn (): Cache => $cache);
    }

    public function render(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO
    {
        $profile = $this->profileOf($owner);
        $layout = $profile instanceof UserProfileModel ? $profile->layout : ProfileLayout::empty();

        $sections = [ProfileRegion::Main->value => [], ProfileRegion::Aside->value => []];
        foreach ($this->ordered($this->registeredSections(), $layout->sections, 'section') as $key => $section) {
            $resolved = $this->resolveSection($key, $section, $layout, $owner, $viewer);

            if ($resolved instanceof ResolvedProfileSectionDTO) {
                $sections[$resolved->region->value][] = $resolved;
            }
        }

        $stats = [];
        foreach ($this->ordered($this->registeredStats(), $layout->stats, 'stat') as $key => $stat) {
            $resolved = $this->resolveStat($key, $stat, $layout, $owner, $viewer);

            if ($resolved instanceof ResolvedProfileStatDTO) {
                $stats[] = $resolved;
            }
        }

        return new ResolvedProfileDTO(
            $this->header($owner, $profile, $viewer),
            $stats,
            $sections[ProfileRegion::Main->value],
            $sections[ProfileRegion::Aside->value],
        );
    }

    private function resolveSection(string $key, ProfileSectionInterface $section, ProfileLayout $layout, ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ResolvedProfileSectionDTO
    {
        try {
            if (! ($layout->sectionEnabled($key) ?? $section->enabledByDefault()) || ! $section->isAvailable()) {
                return null;
            }

            // A module's code may not keep the promise of its PHPDoc
            /** @var array<mixed> $returned */
            $returned = $section->props($owner, $viewer);
            $props = array_filter($returned, is_string(...), ARRAY_FILTER_USE_KEY);

            if (count($props) !== count($returned)) {
                $this->warnOnce('section', $key, 'props', 'A profile section returned props that are not keyed by name; it is not drawn.');

                return null;
            }

            json_encode($props, JSON_THROW_ON_ERROR);

            return new ResolvedProfileSectionDTO(
                $key,
                $this->sections->moduleOf($key),
                $section->component(),
                $section->region(),
                $section->label(),
                $props,
            );
        } catch (NothingToDrawException) {
            // Not a failure: the section has nothing to show, so its title goes too
            return null;
        } catch (JsonException) {
            $this->warnOnce('section', $key, 'json', 'A profile section returned props that cannot be sent to the browser; it is not drawn.');
        } catch (Throwable $exception) {
            // Only the class: a module's message may carry what a person can see
            $this->warnOnce('section', $key, 'failed', 'A profile section failed; it is not drawn.', [
                'exception' => $exception::class,
            ]);
        }

        return null;
    }

    private function resolveStat(string $key, ProfileStatInterface $stat, ProfileLayout $layout, ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ResolvedProfileStatDTO
    {
        try {
            if (! ($layout->statEnabled($key) ?? $stat->enabledByDefault()) || ! $stat->isAvailable()) {
                return null;
            }

            $counted = $stat->stat($owner, $viewer);

            if (! $counted instanceof ProfileStatDTO) {
                return null;
            }

            if ($counted->value < 0) {
                $this->warnOnce('stat', $key, 'value', 'A profile stat counted less than zero; it is not drawn.');

                return null;
            }

            $url = null;
            if ($counted->url !== null) {
                $url = LinkTargetRule::normalize($counted->url, app()->environment(['local', 'testing']));

                if ($url === null) {
                    $this->warnOnce('stat', $key, 'url', 'A profile stat links somewhere a site link may not go; it is not drawn.');

                    return null;
                }
            }

            return new ResolvedProfileStatDTO($key, $stat->label(), $counted->value, $url);
        } catch (Throwable $exception) {
            $this->warnOnce('stat', $key, 'failed', 'A profile stat failed; it is not drawn.', [
                'exception' => $exception::class,
            ]);
        }

        return null;
    }

    private function header(ProfileOwnerDTO $owner, ?UserProfileModel $profile, ?Authenticatable $viewer): ProfileHeaderDTO
    {
        $user = null;
        try {
            $user = UserModel::query()->find($owner->id);
        } catch (Throwable $exception) {
            $this->warnOnce('header', '', 'account', 'The account of a public profile could not be read; its header is drawn without it.', [
                'exception' => $exception::class,
            ]);
        }

        $memberSince = null;
        try {
            $memberSince = $this->timezone->iso($user?->created_at);
        } catch (Throwable $exception) {
            $this->warnOnce('header', '', 'member_since', 'The date an account was created could not be shown; its profile header is drawn without it.', [
                'exception' => $exception::class,
            ]);
        }

        return new ProfileHeaderDTO(
            $owner->handle,
            $owner->publicName,
            $this->filled($user?->avatar_public_id),
            $this->filled($profile?->banner_public_id),
            $memberSince,
            $profile?->show_membership === true && $user instanceof UserModel && $this->isAssociationMember($user),
            $viewer instanceof Authenticatable && $this->isOwner($owner, $viewer),
        );
    }

    /**
     * Fails closed: a membership check that throws shows no badge.
     */
    private function isAssociationMember(UserModel $user): bool
    {
        try {
            return $this->membership->isAssociationMember($user);
        } catch (Throwable $exception) {
            $this->warnOnce('header', '', 'membership', 'The membership of a profile owner could not be checked; the badge is not shown.', [
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    private function isOwner(ProfileOwnerDTO $owner, Authenticatable $viewer): bool
    {
        $id = $viewer->getAuthIdentifier();

        return (is_string($id) || is_int($id)) && (string) $id === $owner->id;
    }

    private function profileOf(ProfileOwnerDTO $owner): ?UserProfileModel
    {
        try {
            return $this->profiles->find($owner->id);
        } catch (Throwable $exception) {
            $this->warnOnce('header', '', 'profile', 'A profile row could not be read; it is drawn with the defaults.', [
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    /**
     * The registered items in the owner's order: first those the layout lists (in its order), then
     * the rest by their default order. A key the layout turns on that nobody registers (a disabled
     * module) is warned about; one it turns off asks for nothing.
     *
     * @template T of object
     *
     * @param  array<string, T>  $registered  key => item, by default order
     * @param  list<ProfileLayoutEntry>  $entries
     * @return array<string, T>
     */
    private function ordered(array $registered, array $entries, string $kind): array
    {
        $ordered = [];

        foreach ($entries as $entry) {
            if (isset($registered[$entry->key])) {
                $ordered[$entry->key] = $registered[$entry->key];
            } elseif ($entry->enabled) {
                $this->warnOnce($kind, $entry->key, 'unknown', sprintf('A profile %s the owner turned on is not registered (its module may be disabled); it is not drawn.', $kind));
            }
        }

        return $ordered + $registered;
    }

    /**
     * @return array<string, ProfileSectionInterface>
     */
    private function registeredSections(): array
    {
        $sections = [];

        try {
            foreach ($this->sections->all() as $section) {
                $sections[$section->key()] = $section;
            }
        } catch (Throwable $exception) {
            $this->warnOnce('section', '', 'registry', 'The profile sections could not be listed; none is drawn.', [
                'exception' => $exception::class,
            ]);

            return [];
        }

        return $sections;
    }

    /**
     * @return array<string, ProfileStatInterface>
     */
    private function registeredStats(): array
    {
        $stats = [];

        try {
            foreach ($this->stats->all() as $stat) {
                $stats[$stat->key()] = $stat;
            }
        } catch (Throwable $exception) {
            $this->warnOnce('stat', '', 'registry', 'The profile stats could not be listed; none is drawn.', [
                'exception' => $exception::class,
            ]);

            return [];
        }

        return $stats;
    }

    private function filled(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warnOnce(string $kind, string $key, string $reason, string $message, array $context = []): void
    {
        $this->warnings->warn('renderer|'.$kind.'|'.$key.'|'.$reason, $message, [
            'key' => mb_substr($key, 0, ProfileKey::MAX_LENGTH),
            'reason' => $reason,
            'kind' => $kind,
            ...$context,
        ]);
    }
}
