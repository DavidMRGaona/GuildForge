<?php

declare(strict_types=1);

namespace App\Infrastructure\Bots;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Bots\DTOs\BotDefinitionDTO;
use App\Application\Bots\DTOs\BotDTO;
use App\Application\Bots\Services\BotAdoptionRule;
use App\Application\Bots\Services\BotRegistryInterface;
use App\Application\Bots\Services\BotsInterface;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\Authorization\Enums\RoleAuditSource;
use App\Domain\Bots\Exceptions\UnknownBotException;
use App\Domain\Bots\ValueObjects\BotKey;
use App\Infrastructure\Authorization\Services\RoleAuditContext;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
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
    ) {}

    public function id(string $key): string
    {
        $definition = $this->registry->find($key);
        if (! $definition instanceof BotDefinitionDTO) {
            throw UnknownBotException::forKey($key);
        }

        $account = $this->find($key) ?? $this->create($key, $definition->label);

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
        if (! $this->registry->find($key) instanceof BotDefinitionDTO) {
            throw UnknownBotException::forKey($key);
        }
        BotKey::of($key);

        if (! Str::isUuid($accountId)) {
            return false;
        }
        $id = strtolower($accountId);

        $adopted = DB::transaction(function () use ($key, $id): ?UserModel {
            // The row is locked before the rule reads it, so nothing changes between check and effects
            $account = UserModel::query()->withBots()->withTrashed()->whereKey($id)->lockForUpdate()->first();
            if (! $account instanceof UserModel) {
                return null;
            }
            if ($account->bot_key === $key) {
                return null;
            }
            if (! $this->adoptionRule->allows($account, $key)) {
                return null;
            }

            $email = (string) $account->email;

            // Before the profile is forgotten: nothing is announced automatically once bot_key is set
            $this->removeEveryRole($account);
            $account->forceFill([
                'bot_key' => $key,
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

        $this->authorization->invalidateCacheFor($adopted);
        // Achievements stop counting the account: the observers no longer announce a bot
        $this->activity->recorded('core.account', $id);
        $this->activity->recorded('core.profile-completed', $id);

        return true;
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

    private function find(string $key): ?UserModel
    {
        return UserModel::query()->withBots()->withTrashed()->where('bot_key', $key)->first();
    }

    private function create(string $key, string $label): UserModel
    {
        BotKey::of($key);
        $this->afterFirstLookup($key);

        // Two calls at once create one account: the second finds the first's after waiting for the lock
        return Cache::lock('bots:create:'.$key, 10)->block(10, function () use ($key, $label): UserModel {
            $existing = $this->find($key);
            if ($existing instanceof UserModel) {
                return $existing;
            }

            $id = (string) Str::uuid();
            $account = new UserModel;
            $account->forceFill([
                'id' => $id,
                'name' => $label,
                'email' => "bot-{$id}@".self::EMAIL_DOMAIN,
                // A password nobody knows: the secret is never kept
                'password' => Hash::make(Str::random(64)),
                'bot_key' => $key,
            ])->save();

            return $this->find($key) ?? throw new InvalidArgumentException('The bot account could not be read back.');
        });
    }
}
