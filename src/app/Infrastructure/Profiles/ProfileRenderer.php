<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Exceptions\NothingToDrawException;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileHeaderDTO;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileRegistrationDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use App\Application\Profiles\DTOs\ResolvedProfileSectionDTO;
use App\Application\Profiles\DTOs\ResolvedProfileStatDTO;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Application\Profiles\Services\ProfileSummaryRendererInterface;
use App\Application\Services\TimezoneServiceInterface;
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
use Illuminate\Contracts\Container\Container;
use JsonException;
use Throwable;

/**
 * Draws a public profile for one person and fails closed (see the interface). Bound scoped, it builds
 * every section and stat from the container itself, from what the registries keep (a class, never an
 * instance), at most once per request or job and only when it is on: nothing a section read in one
 * request reaches the next, and what is off is never built. A profile is drawn on
 * every visit, so a section or stat that keeps failing would log on every request: each key and
 * reason is warned about once a day (DailyWarnings). Warnings carry the key, the reason and an
 * exception only by its class; never props, counts or exception messages.
 */
final class ProfileRenderer implements ProfileRendererInterface, ProfileSummaryRendererInterface
{
    private readonly DailyWarnings $warnings;

    /** @var array<string, ProfileSectionInterface|ProfileStatInterface|null> 'section|key' or 'stat|key' => built instance, null when it could not be */
    private array $built = [];

    public function __construct(
        private readonly ProfileSectionRegistryInterface $sections,
        private readonly ProfileStatRegistryInterface $stats,
        private readonly StoredProfiles $profiles,
        private readonly AssociationMembershipServiceInterface $membership,
        private readonly TimezoneServiceInterface $timezone,
        private readonly Container $container,
        Cache $cache,
    ) {
        $this->warnings = new DailyWarnings(ProfileContributionRegistry::WARNING_PREFIX, static fn (): Cache => $cache);
    }

    public function render(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO
    {
        $profile = $this->profileOf($owner);
        $layout = $profile instanceof UserProfileModel ? $profile->layout : ProfileLayout::empty();
        $user = $this->accountOf($owner);

        $sections = [ProfileRegion::Main->value => [], ProfileRegion::Aside->value => []];
        foreach ($this->ordered($this->registered('section'), $layout->sections, 'section') as $key => $registration) {
            $resolved = $this->resolveSection($key, $registration, $layout, $owner, $viewer);

            if ($resolved instanceof ResolvedProfileSectionDTO) {
                $sections[$resolved->region->value][] = $resolved;
            }
        }

        return new ResolvedProfileDTO(
            $this->header($owner, $profile, $user, $viewer),
            $this->resolveStats($layout, $owner, $viewer, null),
            $sections[ProfileRegion::Main->value],
            $sections[ProfileRegion::Aside->value],
        );
    }

    public function summary(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO
    {
        $profile = $this->profileOf($owner);
        $layout = $profile instanceof UserProfileModel ? $profile->layout : ProfileLayout::empty();

        return new ResolvedProfileDTO(
            $this->header($owner, $profile, $this->accountOf($owner), $viewer),
            $this->resolveStats($layout, $owner, $viewer, ResolvedProfileDTO::CARD_STATS),
            [],
            [],
        );
    }

    /**
     * The stats that can be drawn, in the owner's order; with a limit, none is built or counted once
     * that many are drawn.
     *
     * @return list<ResolvedProfileStatDTO>
     */
    private function resolveStats(ProfileLayout $layout, ProfileOwnerDTO $owner, ?Authenticatable $viewer, ?int $limit): array
    {
        $stats = [];
        foreach ($this->ordered($this->registered('stat'), $layout->stats, 'stat') as $key => $registration) {
            if ($limit !== null && count($stats) >= $limit) {
                break;
            }

            $resolved = $this->resolveStat($key, $registration, $layout, $owner, $viewer);

            if ($resolved instanceof ResolvedProfileStatDTO) {
                $stats[] = $resolved;
            }
        }

        return $stats;
    }

    private function resolveSection(string $key, ProfileRegistrationDTO $registration, ProfileLayout $layout, ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ResolvedProfileSectionDTO
    {
        if (! ($layout->sectionEnabled($key) ?? $registration->enabledByDefault)) {
            return null;
        }

        try {
            $section = $this->build('section', $registration);

            if (! $section instanceof ProfileSectionInterface || ! $section->isAvailable()) {
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
                $registration->module,
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

    private function resolveStat(string $key, ProfileRegistrationDTO $registration, ProfileLayout $layout, ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ResolvedProfileStatDTO
    {
        if (! ($layout->statEnabled($key) ?? $registration->enabledByDefault)) {
            return null;
        }

        try {
            $stat = $this->build('stat', $registration);

            if (! $stat instanceof ProfileStatInterface || ! $stat->isAvailable()) {
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

    /**
     * The section or stat of a registration, built once in this request or job and checked again
     * against what the registry read (an instance built only for that): the same key and, for a
     * section, a valid component path. Null, with a warning, when it cannot be built or differs.
     *
     * @param  'section'|'stat'  $kind
     */
    private function build(string $kind, ProfileRegistrationDTO $registration): ProfileSectionInterface|ProfileStatInterface|null
    {
        $id = $kind.'|'.$registration->key;

        if (array_key_exists($id, $this->built)) {
            return $this->built[$id];
        }

        $built = null;

        try {
            $instance = $this->container->make($registration->class);

            $valid = $kind === 'section'
                ? $instance instanceof ProfileSectionInterface && $instance->key() === $registration->key && preg_match(ProfileSectionRegistry::COMPONENT_PATTERN, $instance->component()) === 1
                : $instance instanceof ProfileStatInterface && $instance->key() === $registration->key;

            if ($valid) {
                $built = $instance;
            } else {
                $this->warnOnce($kind, $registration->key, 'build', sprintf('A profile %s was built differently from when it was registered; it is not drawn.', $kind));
            }
        } catch (Throwable $exception) {
            $this->warnOnce($kind, $registration->key, 'build', sprintf('A profile %s could not be built; it is not drawn.', $kind), [
                'exception' => $exception::class,
            ]);
        }

        return $this->built[$id] = $built;
    }

    /**
     * The owner's account, read once: the public name comes from it, never from the caller.
     */
    private function accountOf(ProfileOwnerDTO $owner): ?UserModel
    {
        try {
            return UserModel::query()->find($owner->id);
        } catch (Throwable $exception) {
            $this->warnOnce('header', '', 'account', 'The account of a public profile could not be read; its header is drawn without it.', [
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function header(ProfileOwnerDTO $owner, ?UserProfileModel $profile, ?UserModel $user, ?Authenticatable $viewer): ProfileHeaderDTO
    {
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
            $this->filled($user?->display_name),
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
     * module) is warned about; one it turns off, or that is not a valid key, asks for nothing.
     *
     * @param  array<string, ProfileRegistrationDTO>  $registered  key => registration, by default order
     * @param  list<ProfileLayoutEntry>  $entries
     * @return array<string, ProfileRegistrationDTO>
     */
    private function ordered(array $registered, array $entries, string $kind): array
    {
        $ordered = [];

        foreach ($entries as $entry) {
            if (isset($registered[$entry->key])) {
                $ordered[$entry->key] = $registered[$entry->key];
            } elseif ($entry->enabled && ProfileKey::tryFrom($entry->key) instanceof ProfileKey) {
                // Only something shaped like a key: a forged layout never writes free text to the log
                $this->warnOnce($kind, $entry->key, 'unknown', sprintf('A profile %s the owner turned on is not registered (its module may be disabled); it is not drawn.', $kind));
            }
        }

        return $ordered + $registered;
    }

    /**
     * The registrations of a kind, key => registration, by default order.
     *
     * @param  'section'|'stat'  $kind
     * @return array<string, ProfileRegistrationDTO>
     */
    private function registered(string $kind): array
    {
        $registered = [];

        try {
            foreach (($kind === 'section' ? $this->sections : $this->stats)->all() as $registration) {
                $registered[$registration->key] = $registration;
            }
        } catch (Throwable $exception) {
            $this->warnOnce($kind, '', 'registry', sprintf('The profile %ss could not be listed; none is drawn.', $kind), [
                'exception' => $exception::class,
            ]);

            return [];
        }

        return $registered;
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
