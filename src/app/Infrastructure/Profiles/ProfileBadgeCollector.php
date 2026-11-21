<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileBadgeSourceInterface;
use App\Application\Profiles\DTOs\ProfileBadgeDTO;
use App\Application\Profiles\DTOs\ProfileBadgeSourceRegistrationDTO;
use App\Application\Profiles\ProfileBadgeIcons;
use App\Application\Profiles\Services\ProfileBadgesInterface;
use App\Application\Profiles\Services\ProfileBadgeSourceRegistryInterface;
use App\Domain\Profiles\ValueObjects\ProfileKey;
use App\Domain\SiteBlocks\LinkTargetRule;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Asks every badge source about accounts whose profiles may already be shown (core#776) and keeps
 * only the badges that can be drawn. It never decides who may see a profile: ProfileBadges asks the
 * access rule first, and the renderer is only called once the page or the card did.
 *
 * Bound scoped: each source is built from the container once per request or job, from what the
 * registry keeps (a class, never an instance). It fails closed per source and per badge, each
 * failure warned about once a day per source class and reason (DailyWarnings), with the exception
 * only by its class: a source's message may carry what a person can see.
 */
final class ProfileBadgeCollector
{
    /**
     * Invisible format characters (Unicode Cf): bidirectional marks, which could turn the text around
     * them, and zero-width ones, which could make a label that looks empty. Dropped.
     */
    private const string INVISIBLE = '/\p{Cf}/u';

    private readonly DailyWarnings $warnings;

    /** @var array<string, ProfileBadgeSourceInterface|null> class => built source, null when it could not be */
    private array $built = [];

    public function __construct(
        private readonly ProfileBadgeSourceRegistryInterface $registry,
        private readonly Container $container,
        Cache $cache,
    ) {
        $this->warnings = new DailyWarnings(ProfileContributionRegistry::WARNING_PREFIX, static fn (): Cache => $cache);
    }

    /**
     * Whether any source is registered, so a page without one reads nothing at all.
     */
    public function hasSources(): bool
    {
        return $this->registrations() !== [];
    }

    /**
     * The badges of each account, at most ProfileBadgesInterface::MAX_PER_ACCOUNT, in the order of
     * the sources and then of each source's answer.
     *
     * @param  list<string>  $userIds  accounts whose profile may be shown, in lower case, each once
     * @return array<string, list<ProfileBadgeDTO>> in the order of the ids; accounts without badges left out
     */
    public function collect(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $asked = array_flip($userIds);
        /** @var array<string, list<ProfileBadgeDTO>> $badges */
        $badges = [];
        /** @var array<string, array<string, true>> $keys */
        $keys = [];

        foreach ($this->registrations() as $registration) {
            foreach ($this->ask($registration, $userIds) ?? [] as $userId => $given) {
                // Ids are asked about in lower case; a source may answer them as its database keeps them
                $userId = strtolower((string) $userId);

                // Only who was asked about: anybody else may have a profile this person may not see
                if (! isset($asked[$userId])) {
                    continue;
                }

                if (! is_array($given)) {
                    $this->warn($registration, 'shape', 'A profile badge source answered an account with something that is not a list; it gives nothing for that account.');

                    continue;
                }

                $given = array_values($given);
                if (count($given) > ProfileBadgesInterface::MAX_PER_ACCOUNT) {
                    $this->warn($registration, 'too_many', 'A profile badge source gave an account more badges than are shown; only the first ones are.');
                    $given = array_slice($given, 0, ProfileBadgesInterface::MAX_PER_ACCOUNT);
                }

                foreach ($given as $item) {
                    if (count($badges[$userId] ?? []) >= ProfileBadgesInterface::MAX_PER_ACCOUNT) {
                        break;
                    }

                    $badge = $this->checked($item, $registration);

                    if (! $badge instanceof ProfileBadgeDTO) {
                        continue;
                    }

                    if (isset($keys[$userId][$badge->key])) {
                        $this->warn($registration, 'duplicate', 'A profile badge has the key of another badge of the same account; it is left out.');

                        continue;
                    }

                    $keys[$userId][$badge->key] = true;
                    $badges[$userId][] = $badge;
                }
            }
        }

        $ordered = [];
        foreach ($userIds as $userId) {
            if (isset($badges[$userId])) {
                $ordered[$userId] = $badges[$userId];
            }
        }

        return $ordered;
    }

    /**
     * What a source answered, or null (with a warning) when it cannot be built or throws.
     *
     * @param  list<string>  $userIds
     * @return array<array-key, mixed>|null
     */
    private function ask(ProfileBadgeSourceRegistrationDTO $registration, array $userIds): ?array
    {
        $source = $this->build($registration);

        if (! $source instanceof ProfileBadgeSourceInterface) {
            return null;
        }

        try {
            // A module's code may not keep the promise of its PHPDoc: what is inside is checked
            /** @var array<array-key, mixed> */
            return $source->badgesFor($userIds);
        } catch (Throwable $exception) {
            $this->warn($registration, 'failed', 'A profile badge source failed; it gives no badges.', [
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function build(ProfileBadgeSourceRegistrationDTO $registration): ?ProfileBadgeSourceInterface
    {
        if (array_key_exists($registration->class, $this->built)) {
            return $this->built[$registration->class];
        }

        $built = null;

        try {
            $instance = $this->container->make($registration->class);

            if ($instance instanceof ProfileBadgeSourceInterface) {
                $built = $instance;
            } else {
                $this->warn($registration, 'build', 'A registered profile badge source does not implement ProfileBadgeSourceInterface; it gives no badges.');
            }
        } catch (Throwable $exception) {
            $this->warn($registration, 'build', 'A profile badge source could not be built; it gives no badges.', [
                'exception' => $exception::class,
            ]);
        }

        return $this->built[$registration->class] = $built;
    }

    /**
     * The badge as it can be drawn, or null (with a warning) when it cannot.
     */
    private function checked(mixed $item, ProfileBadgeSourceRegistrationDTO $registration): ?ProfileBadgeDTO
    {
        if (! $item instanceof ProfileBadgeDTO) {
            return $this->refuse($registration, 'type', 'A profile badge source gave something that is not a ProfileBadgeDTO; it is left out.');
        }

        $key = ProfileKey::tryFrom($item->key);
        if (! $key instanceof ProfileKey) {
            return $this->refuse($registration, 'key', 'A profile badge has an invalid key; it is left out.');
        }

        // A badge named after somebody else could pass for theirs
        if ($key->owner() !== ($registration->module ?? ProfileKey::CORE_OWNER) || $registration->module === ProfileKey::CORE_OWNER) {
            return $this->refuse($registration, 'owner', 'A profile badge key does not start with its module name; it is left out.');
        }

        $label = $this->text($item->label, ProfileBadgeDTO::LABEL_MAX_LENGTH);
        if ($label === '') {
            return $this->refuse($registration, 'label', 'A profile badge has no label; it is left out.');
        }

        if (! ProfileBadgeIcons::isKnown($item->icon)) {
            return $this->refuse($registration, 'icon', 'A profile badge names an icon the browser does not draw; it is left out.');
        }

        $imageUrl = null;
        if ($item->imageUrl !== null) {
            $imageUrl = $this->cloudinaryImage($item->imageUrl);

            if ($imageUrl === null) {
                return $this->refuse($registration, 'image', 'A profile badge image is not an https address of Cloudinary; it is left out.');
            }
        }

        $url = null;
        if ($item->url !== null) {
            $url = LinkTargetRule::normalize($item->url, app()->environment(['local', 'testing']));

            if ($url === null) {
                return $this->refuse($registration, 'url', 'A profile badge links somewhere a site link may not go; it is left out.');
            }
        }

        return new ProfileBadgeDTO(
            $key->value,
            $label,
            $this->text($item->description, ProfileBadgeDTO::DESCRIPTION_MAX_LENGTH),
            $item->icon,
            $item->colour,
            $imageUrl,
            $url,
        );
    }

    /**
     * Plain text on one line: invisible format characters dropped, control characters and runs of blanks as
     * one space, cut to the limit in characters.
     */
    private function text(string $text, int $limit): string
    {
        $clean = preg_replace(self::INVISIBLE, '', $text);
        $clean = $clean === null ? '' : preg_replace('/[\p{Cc}\s]+/u', ' ', $clean);

        return $clean === null ? '' : trim(mb_substr(trim($clean), 0, $limit));
    }

    /**
     * The image as it can be drawn: an `https` address of Cloudinary on the default port, the only host
     * the content security policy lets the page load images from. Null for anything else.
     */
    private function cloudinaryImage(string $url): ?string
    {
        $normalized = LinkTargetRule::normalize($url);
        $parts = $normalized === null ? false : parse_url($normalized);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['port'])) {
            return null;
        }

        return strtolower($parts['host'] ?? '') === CloudinaryImageUrlBuilder::HOST ? $normalized : null;
    }

    /**
     * @return list<ProfileBadgeSourceRegistrationDTO>
     */
    private function registrations(): array
    {
        try {
            return $this->registry->all();
        } catch (Throwable $exception) {
            $this->warnings->warn('badges|registry|failed', 'The profile badge sources could not be listed; no badge is shown.', [
                'reason' => 'registry',
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    private function refuse(ProfileBadgeSourceRegistrationDTO $registration, string $reason, string $message): null
    {
        $this->warn($registration, $reason, $message);

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warn(ProfileBadgeSourceRegistrationDTO $registration, string $reason, string $message, array $context = []): void
    {
        $this->warnings->warn('badges|'.$registration->class.'|'.$reason, $message, [
            'class' => $registration->class,
            'module' => $registration->module,
            'reason' => $reason,
            ...$context,
        ]);
    }
}
