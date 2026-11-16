<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\DescribesProfileStat;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileLayoutOptionDTO;
use App\Application\Profiles\DTOs\ProfileRegistrationDTO;
use App\Application\Profiles\Services\ProfileLayoutOptionsInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Domain\Profiles\ValueObjects\ProfileKey;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Builds every registered section and stat from the container, like the renderer, once per request or
 * job (bound scoped), only to read what the editor shows: label, description and, for sections, where
 * they are drawn. Never their props or counts. Fails closed and warns once a day per key and reason
 * (DailyWarnings, the profiles' prefix); warnings carry the key, the reason and an exception only by
 * its class.
 */
final class ProfileLayoutOptions implements ProfileLayoutOptionsInterface
{
    private readonly DailyWarnings $warnings;

    /** @var list<ProfileLayoutOptionDTO>|null */
    private ?array $sections = null;

    /** @var list<ProfileLayoutOptionDTO>|null */
    private ?array $stats = null;

    public function __construct(
        private readonly ProfileSectionRegistryInterface $sectionRegistry,
        private readonly ProfileStatRegistryInterface $statRegistry,
        private readonly Container $container,
        Cache $cache,
    ) {
        $this->warnings = new DailyWarnings(ProfileContributionRegistry::WARNING_PREFIX, static fn (): Cache => $cache);
    }

    public function sections(): array
    {
        return $this->sections ??= $this->optionsOf('section');
    }

    public function stats(): array
    {
        return $this->stats ??= $this->optionsOf('stat');
    }

    /**
     * @param  'section'|'stat'  $kind
     * @return list<ProfileLayoutOptionDTO>
     */
    private function optionsOf(string $kind): array
    {
        try {
            $registrations = ($kind === 'section' ? $this->sectionRegistry : $this->statRegistry)->all();
        } catch (Throwable $exception) {
            $this->warn($kind, '', 'registry', sprintf('The profile %ss could not be listed; the editor offers none.', $kind), $exception);

            return [];
        }

        $options = [];

        foreach ($registrations as $registration) {
            $option = $this->optionOf($kind, $registration);

            if ($option instanceof ProfileLayoutOptionDTO) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * @param  'section'|'stat'  $kind
     */
    private function optionOf(string $kind, ProfileRegistrationDTO $registration): ?ProfileLayoutOptionDTO
    {
        try {
            $item = $this->container->make($registration->class);

            if ($kind === 'section') {
                if (! $item instanceof ProfileSectionInterface || $item->key() !== $registration->key || preg_match(ProfileSectionRegistry::COMPONENT_PATTERN, $item->component()) !== 1) {
                    $this->warn($kind, $registration->key, 'build', 'A profile section was built differently from when it was registered; the editor does not offer it.');

                    return null;
                }

                if (! $item->isAvailable()) {
                    return null;
                }

                return new ProfileLayoutOptionDTO(
                    $registration->key,
                    $item->label(),
                    $item->description(),
                    $registration->enabledByDefault,
                    $item->region(),
                    $registration->module,
                    $item->component(),
                );
            }

            if (! $item instanceof ProfileStatInterface || $item->key() !== $registration->key) {
                $this->warn($kind, $registration->key, 'build', 'A profile stat was built differently from when it was registered; the editor does not offer it.');

                return null;
            }

            if (! $item->isAvailable()) {
                return null;
            }

            // Optional: a stat written for the contract of core#742 has no description
            $description = $item instanceof DescribesProfileStat ? $item->description() : '';

            return new ProfileLayoutOptionDTO($registration->key, $item->label(), $description, $registration->enabledByDefault);
        } catch (Throwable $exception) {
            $this->warn($kind, $registration->key, 'failed', sprintf('A profile %s failed while the editor read it; it is not offered.', $kind), $exception);

            return null;
        }
    }

    private function warn(string $kind, string $key, string $reason, string $message, ?Throwable $exception = null): void
    {
        $this->warnings->warn('editor|'.$kind.'|'.$key.'|'.$reason, $message, [
            'key' => mb_substr($key, 0, ProfileKey::MAX_LENGTH),
            'reason' => $reason,
            'kind' => $kind,
            ...($exception instanceof Throwable ? ['exception' => $exception::class] : []),
        ]);
    }
}
