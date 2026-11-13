<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Domain\Events\UserDeactivated;
use App\Domain\Events\UserRestored;
use App\Infrastructure\Media\Sources\AvatarMediaSource;
use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use App\Notifications\Contracts\NamesMailRecipient;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $name
 * @property string|null $display_name
 * @property string $email
 * @property string|null $pending_email
 * @property string $password
 * @property string|null $avatar_public_id
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserModel extends Authenticatable implements FilamentUser, HasAvatar, MustVerifyEmail
{
    use DeletesCloudinaryImages;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;
    use SoftDeletes;
    use StoresInstantsInStorageTimezone;

    private const int PANEL_AVATAR_SIZE = 64;

    /**
     * Deactivating (soft deleting) and restoring tell the modules after the commit, wherever
     * they happen: panel, commands or code. A permanent deletion does not.
     */
    protected static function booted(): void
    {
        static::deleted(static function (self $user): void {
            if ($user->isForceDeleting()) {
                return;
            }

            $userId = (string) $user->getKey();
            DB::afterCommit(static function () use ($userId): void {
                Event::dispatch(new UserDeactivated($userId));
            });
        });

        static::restored(static function (self $user): void {
            $userId = (string) $user->getKey();
            DB::afterCommit(static function () use ($userId): void {
                Event::dispatch(new UserRestored($userId));
            });
        });
    }

    /** @var array<string> */
    protected array $cloudinaryImageFields = ['avatar_public_id'];

    /**
     * Avatars are never chosen from the media library, so never shared: they go at once, which anonymizing
     * an account relies on.
     */
    protected function sharesCloudinaryImages(): bool
    {
        return false;
    }

    /**
     * Only a file in the avatar folders goes at once; any other path (never an upload of this field) may be
     * shared, so it is released and kept while anyone uses it.
     */
    protected function deleteFromCloudinary(string $publicId): void
    {
        $path = ltrim(str_replace('\\', '/', $publicId), '/');

        if (array_any(AvatarMediaSource::FOLDERS, static fn (string $folder): bool => str_starts_with($path, $folder) && ! str_contains($path, '..'))) {
            Storage::disk('images')->delete($publicId);

            return;
        }

        resolve(MediaFileReleaserInterface::class)->release($publicId);
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The attributes that are mass-assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'display_name',
        'email',
        'pending_email',
        'password',
        'avatar_public_id',
        'anonymized_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * The panel opens for admin.access from any role, never for a role name: Filament, the
     * maintenance bypass and UserService::canAccessPanel() must give the same answer.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return app(AuthorizationServiceInterface::class)->can($this, 'admin.access');
    }

    /**
     * The panel shows the picture uploaded in the public profile; without one Filament draws
     * the initials. 64px covers the 32px avatar of the top bar on high-density screens.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_public_id === null || $this->avatar_public_id === '') {
            return null;
        }

        return app(CloudinaryImageUrlBuilder::class)->avatar($this->avatar_public_id, self::PANEL_AVATAR_SIZE);
    }

    /**
     * Mail goes to the account's address unless the notification implements NamesMailRecipient:
     * VerifyPendingEmailNotification must reach the new address, or anyone could confirm an
     * address they do not own from their current inbox.
     */
    public function routeNotificationForMail(?Notification $notification = null): string
    {
        if ($notification instanceof NamesMailRecipient) {
            return $notification->mailRecipient();
        }

        return $this->email;
    }

    /**
     * Check if user has admin role.
     * Used as fast-path in AuthorizationService.
     */
    public function isAdmin(): bool
    {
        return $this->roles()->where('name', 'admin')->exists();
    }

    /**
     * @return BelongsToMany<RoleModel, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            RoleModel::class,
            'user_role',
            'user_id',
            'role_id'
        )->withTimestamps();
    }

    /**
     * @return HasMany<RoleAuditEntryModel, $this>
     */
    public function roleAuditEntries(): HasMany
    {
        return $this->hasMany(RoleAuditEntryModel::class, 'user_id');
    }

    /**
     * Impersonations of this account (it was the one viewed).
     *
     * @return HasMany<ImpersonationLogEntryModel, $this>
     */
    public function impersonationsAsTarget(): HasMany
    {
        return $this->hasMany(ImpersonationLogEntryModel::class, 'target_id');
    }

    /**
     * Impersonations this account made (it was the administrator).
     *
     * @return HasMany<ImpersonationLogEntryModel, $this>
     */
    public function impersonationsAsImpersonator(): HasMany
    {
        return $this->hasMany(ImpersonationLogEntryModel::class, 'impersonator_id');
    }

    /**
     * Normalize email to lowercase to ensure case-insensitive lookups on PostgreSQL.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower($value),
        );
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
