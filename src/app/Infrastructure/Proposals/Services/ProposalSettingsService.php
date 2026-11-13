<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Services;

use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalAccessLevel;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use JsonException;

final readonly class ProposalSettingsService implements ProposalSettingsServiceInterface
{
    public const string MAX_PENDING = 'proposals_max_pending';

    public const string MAX_OPEN_DRAFTS = 'proposals_max_open_drafts';

    public const string REJECTED_RETENTION_DAYS = 'proposals_rejected_retention_days';

    public const string SUBMISSIONS_PER_HOUR = 'proposals_submissions_per_hour';

    public function __construct(private SettingsServiceInterface $settings) {}

    /**
     * Setting key of one type's value: proposals_events_enabled, proposals_articles_roles…
     */
    public static function key(ProposalType $type, string $name): string
    {
        return 'proposals_'.$type->value.'_'.$name;
    }

    public function settings(): ProposalSettingsDTO
    {
        return new ProposalSettingsDTO(
            events: $this->typeSettings(ProposalType::Event),
            articles: $this->typeSettings(ProposalType::Article),
            maxPending: $this->number(self::MAX_PENDING, ProposalSettingsDTO::DEFAULT_MAX_PENDING, ProposalSettingsDTO::MAX_LIMIT),
            maxOpenDrafts: $this->number(self::MAX_OPEN_DRAFTS, ProposalSettingsDTO::DEFAULT_MAX_OPEN_DRAFTS, ProposalSettingsDTO::MAX_LIMIT),
            rejectedRetentionDays: $this->number(self::REJECTED_RETENTION_DAYS, ProposalSettingsDTO::DEFAULT_REJECTED_RETENTION_DAYS, ProposalSettingsDTO::MAX_RETENTION_DAYS),
            submissionsPerHour: $this->number(self::SUBMISSIONS_PER_HOUR, ProposalSettingsDTO::DEFAULT_SUBMISSIONS_PER_HOUR, ProposalSettingsDTO::MAX_LIMIT),
        );
    }

    public function save(ProposalSettingsDTO $settings): void
    {
        foreach (ProposalType::cases() as $type) {
            $typeSettings = $settings->forType($type);

            $this->settings->set(self::key($type, 'enabled'), $typeSettings->enabled ? '1' : '0');
            $this->settings->set(self::key($type, 'access_level'), ($typeSettings->accessLevel ?? ProposalAccessLevel::Registered)->value);
            $this->settings->set(self::key($type, 'roles'), json_encode($this->existingRoles($typeSettings->roles), JSON_THROW_ON_ERROR));
            $this->settings->set(self::key($type, 'show_proposer'), $type === ProposalType::Event && $typeSettings->showProposer ? '1' : '0');
        }

        $this->settings->set(self::MAX_PENDING, (string) $this->clamp($settings->maxPending, ProposalSettingsDTO::MAX_LIMIT));
        $this->settings->set(self::MAX_OPEN_DRAFTS, (string) $this->clamp($settings->maxOpenDrafts, ProposalSettingsDTO::MAX_LIMIT));
        $this->settings->set(self::REJECTED_RETENTION_DAYS, (string) $this->clamp($settings->rejectedRetentionDays, ProposalSettingsDTO::MAX_RETENTION_DAYS));
        $this->settings->set(self::SUBMISSIONS_PER_HOUR, (string) $this->clamp($settings->submissionsPerHour, ProposalSettingsDTO::MAX_LIMIT));
    }

    private function typeSettings(ProposalType $type): ProposalTypeSettingsDTO
    {
        $level = $this->settings->get(self::key($type, 'access_level'));

        return new ProposalTypeSettingsDTO(
            enabled: $this->flag(self::key($type, 'enabled')),
            accessLevel: $level === null || $level === '' ? ProposalAccessLevel::Registered : ProposalAccessLevel::tryFrom((string) $level),
            roles: $this->roles(self::key($type, 'roles')),
            showProposer: $type === ProposalType::Event && $this->flag(self::key($type, 'show_proposer')),
        );
    }

    /**
     * Only an explicit "1" turns a switch on.
     */
    private function flag(string $key): bool
    {
        return (string) $this->settings->get($key, '0') === '1';
    }

    /**
     * @return list<string>
     */
    private function roles(string $key): array
    {
        $stored = (string) $this->settings->get($key, '');

        if ($stored === '') {
            return [];
        }

        try {
            $decoded = json_decode($stored, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter($decoded, static fn (mixed $role): bool => is_string($role) && $role !== '')));
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function existingRoles(array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        /** @var list<string> $existing */
        $existing = RoleModel::query()->whereIn('name', $roles)->orderBy('name')->pluck('name')->all();

        return $existing;
    }

    private function number(string $key, int $default, int $max): int
    {
        $stored = $this->settings->get($key);

        if (! is_scalar($stored) || preg_match('/^\d+$/', (string) $stored) !== 1) {
            return $default;
        }

        $value = (int) $stored;

        return $value >= ProposalSettingsDTO::MIN_LIMIT && $value <= $max ? $value : $default;
    }

    private function clamp(int $value, int $max): int
    {
        return max(ProposalSettingsDTO::MIN_LIMIT, min($max, $value));
    }
}
