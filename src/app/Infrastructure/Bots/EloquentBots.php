<?php

declare(strict_types=1);

namespace App\Infrastructure\Bots;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Bots\DTOs\BotDefinitionDTO;
use App\Application\Bots\DTOs\BotDTO;
use App\Application\Bots\Services\BotRegistryInterface;
use App\Application\Bots\Services\BotsInterface;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\Authorization\Enums\RoleAuditSource;
use App\Domain\Bots\Exceptions\UnknownBotException;
use App\Domain\Bots\ValueObjects\BotKey;
use App\Infrastructure\Activity\Sources\AccountActivitySource;
use App\Infrastructure\Activity\Sources\ProfileCompletedActivitySource;
use App\Infrastructure\Authorization\Services\RoleAuditContext;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Not final only so a test can hook between the first read and the lock (see afterFirstLookup()).
 */
class EloquentBots implements BotsInterface
{
    public const string EMAIL_DOMAIN = 'bots.invalid';

    private readonly DailyWarnings $warnings;

    public function __construct(
        private readonly BotRegistryInterface $registry,
        private readonly SiteBrandingServiceInterface $branding,
        private readonly BotAdoptionRule $adoptionRule,
        private readonly UserProfileServiceInterface $profiles,
        private readonly ActivityPublisherInterface $activity,
        private readonly WebPushSubscriptionServiceInterface $webPush,
        private readonly RoleAuditLoggerInterface $audit,
        private readonly RoleAuditContext $auditContext,
        private readonly AuthorizationServiceInterface $authorization,
    ) {
        $this->warnings = new DailyWarnings(BotRegistry::WARNING_PREFIX, static fn () => Cache::store());
    }

    public function id(string $key): string
    {
        $definition = $this->registry->find($key);
        if (! $definition instanceof BotDefinitionDTO) {
            throw UnknownBotException::forKey($key);
        }

        $account = $this->find($key) ?? $this->create($definition);

        return strtolower((string) $account->getKey());
    }

    public function isBot(?string $accountId): bool
    {
        if ($accountId === null || ! Str::isUuid($accountId)) {
            return false;
        }

        return UserModel::query()->withBots()->whereKey(strtolower($accountId))->whereNotNull('bot_key')->exists();
    }

    public function describe(array $ids): array
    {
        $valid = [];
        foreach ($ids as $id) {
            if (Str::isUuid($id)) {
                $valid[strtolower($id)] = true;
            }
        }
        if ($valid === []) {
            return [];
        }

        $siteName = null;
        $result = [];
        $accounts = UserModel::query()->withBots()->withTrashed()
            ->whereIn('id', array_keys($valid))
            ->whereNotNull('bot_key')
            ->get();

        foreach ($accounts as $account) {
            $key = (string) $account->bot_key;
            $own = $account->display_name;
            $name = is_string($own) && $own !== '' ? $own : ($siteName ??= $this->branding->siteName());
            $id = strtolower((string) $account->getKey());

            $result[$id] = new BotDTO(
                id: $id,
                key: $key,
                module: BotKey::of($key)->module(),
                name: $name,
                avatarPublicId: $account->avatar_public_id,
            );
        }

        return $result;
    }

    public function adopt(string $key, string $accountId): bool
    {
        $definition = $this->registry->find($key);
        if (! $definition instanceof BotDefinitionDTO) {
            throw UnknownBotException::forKey($key);
        }
        BotKey::of($key);

        if (! Str::isUuid($accountId)) {
            return false;
        }
        $id = strtolower($accountId);

        $adopted = DB::transaction(function () use ($key, $id, $definition): ?UserModel {
            // The row is locked before the rule reads it, so nothing changes between check and effects
            $account = UserModel::query()->withBots()->withTrashed()->whereKey($id)->lockForUpdate()->first();
            if (! $account instanceof UserModel) {
                return null;
            }
            if ($account->bot_key === $key) {
                return null;
            }
            // The key is unique: another account (a bot made by id() first) already holds it
            $taken = UserModel::query()->withBots()->withTrashed()
                ->where('bot_key', $key)->whereKeyNot($id)->exists();
            if ($taken) {
                $this->refused($id, $key, 'key_taken');

                return null;
            }
            $refusal = $this->adoptionRule->refusal($account, $key);
            if ($refusal !== null) {
                $this->refused($id, $key, $refusal);

                return null;
            }

            $email = (string) $account->email;

            // Before the profile is forgotten: nothing is announced automatically once bot_key is set
            $this->removeEveryRole($account);
            $account->forceFill([
                'bot_key' => $key,
                // The bot's own name or, when null, the site's name read when shown (never frozen here)
                'name' => $definition->label,
                'display_name' => $definition->name,
                // A password nobody knows, and no session or remember cookie left
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'deleted_at' => null,
            ])->saveQuietly();

            $this->profiles->forget($id);
            DB::table('site_notifications')->where('user_id', $id)->delete();
            DB::table('user_site_notification_seen')->where('user_id', $id)->delete();
            $this->webPush->forgetUser($id);
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return $account;
        });

        if (! $adopted instanceof UserModel) {
            // Idempotent: the bot of this very key is a success, nothing more is done
            return UserModel::query()->withBots()->withTrashed()->whereKey($id)->where('bot_key', $key)->exists();
        }

        // Achievements stop counting the account: the observers no longer announce a bot. First, because
        // the publisher never throws and the cache refresh may: a retry finds the bot already done.
        $this->activity->recorded(AccountActivitySource::TYPE, $id);
        $this->activity->recorded(ProfileCompletedActivitySource::TYPE, $id);
        $this->authorization->invalidateCacheFor($adopted);

        return true;
    }

    private function refused(string $accountId, string $key, string $reason): void
    {
        $this->warnings->warn(
            "adoption-refused:{$accountId}:{$key}:{$reason}",
            'Bot adoption refused',
            ['user_id' => $accountId, 'key' => $key, 'reason' => $reason],
        );
    }

    private function removeEveryRole(UserModel $account): void
    {
        /** @var list<string> $names */
        $names = $account->roles()->pluck('roles.name')->all();

        $this->auditContext->actingFor(null, function () use ($account, $names): void {
            foreach ($names as $name) {
                $this->audit->roleRemoved($account, $name);
            }
        }, RoleAuditSource::System);

        $account->roles()->detach();
    }

    /**
     * Test seam: runs after the first read found nothing and before the lock is taken, where another
     * process may create the same bot. Does nothing in production.
     */
    protected function afterFirstLookup(string $key): void {}

    /**
     * Test seam: runs after the read inside the lock and before the insert, where a process that did
     * not wait for the lock may create the same bot. Does nothing in production.
     */
    protected function beforeInsert(string $key): void {}

    private function find(string $key): ?UserModel
    {
        return UserModel::query()->withBots()->withTrashed()->where('bot_key', $key)->first();
    }

    private function create(BotDefinitionDTO $definition): UserModel
    {
        $key = $definition->key;
        BotKey::of($key);
        $this->afterFirstLookup($key);

        // Two calls at once create one account: the second finds the first's after waiting for the lock
        return Cache::lock('bots:create:'.$key, 10)->block(10, function () use ($key, $definition): UserModel {
            $existing = $this->find($key);
            if ($existing instanceof UserModel) {
                return $existing;
            }

            $this->beforeInsert($key);

            $id = (string) Str::uuid();
            $account = new UserModel;
            $account->forceFill([
                'id' => $id,
                'name' => $definition->label,
                // null: the site's name, read when shown
                'display_name' => $definition->name,
                'email' => "bot-{$id}@".self::EMAIL_DOMAIN,
                // A password nobody knows: the secret is never kept
                'password' => Hash::make(Str::random(64)),
                'bot_key' => $key,
            ]);

            try {
                // Its own (nested: savepoint) transaction, so a unique violation does not abort a caller's
                DB::transaction(static fn (): bool => $account->save());
            } catch (UniqueConstraintViolationException $e) {
                // Another process made it meanwhile (the lock is released before a caller's commit)
                return $this->find($key) ?? throw $e;
            }

            return $this->find($key) ?? throw new InvalidArgumentException('The bot account could not be read back.');
        });
    }
}
