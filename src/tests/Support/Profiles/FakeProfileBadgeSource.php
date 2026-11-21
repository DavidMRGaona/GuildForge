<?php

declare(strict_types=1);

namespace Tests\Support\Profiles;

use App\Application\Profiles\Contracts\ProfileBadgeSourceInterface;
use App\Application\Profiles\DTOs\ProfileBadgeDTO;
use App\Domain\Profiles\ProfileBadgeColour;
use Closure;

/**
 * A profile badge source for tests: it answers what its closure gives for the accounts asked about
 * and remembers every call. Without a closure each account gets one badge, `<module>.first`.
 */
final class FakeProfileBadgeSource implements ProfileBadgeSourceInterface
{
    /** @var list<list<string>> */
    public array $calls = [];

    /**
     * @param  (Closure(list<string>): mixed)|null  $answer
     */
    public function __construct(
        private readonly string $module = 'achievements',
        private readonly ?Closure $answer = null,
    ) {}

    public function badgesFor(array $userIds): array
    {
        $this->calls[] = $userIds;

        if ($this->answer instanceof Closure) {
            /** @var array<string, list<ProfileBadgeDTO>> */
            return ($this->answer)($userIds);
        }

        $badges = [];
        foreach ($userIds as $userId) {
            $badges[$userId] = [self::badge($this->module.'.first')];
        }

        return $badges;
    }

    public static function badge(
        string $key,
        string $label = 'Primera partida',
        string $description = 'Jugó su primera partida en la asociación.',
        string $icon = 'trophy',
        ProfileBadgeColour $colour = ProfileBadgeColour::Amber,
        ?string $imageUrl = null,
        ?string $url = null,
    ): ProfileBadgeDTO {
        return new ProfileBadgeDTO($key, $label, $description, $icon, $colour, $imageUrl, $url);
    }
}
