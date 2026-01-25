<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string|null $display_name
 * @property string $email
 * @property string|null $pending_email
 * @property string $password
 * @property string|null $avatar_public_id
 * @property UserRole|null $role
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserModel extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use DeletesCloudinaryImages;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    /** @var array<string> */
    protected array $cloudinaryImageFields = ['avatar_public_id'];

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
        'role',
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
            'role' => UserRole::class,
            'anonymized_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Check new role-based system first (using direct relationship query)
        if ($this->roles()->whereIn('name', ['admin', 'editor'])->exists()) {
            return true;
        }

        // Fallback to old enum-based system during migration
        return $this->role?->canAccessPanel() ?? false;
    }

    /**
     * Check if user has admin role.
     * Used as fast-path in AuthorizationService.
     */
    public function isAdmin(): bool
    {
        // Check new role-based system first
        if ($this->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        // Fallback to old enum-based system during migration
        return $this->role === UserRole::Admin;
    }

    /**
     * @deprecated Use AuthorizationService::hasRole() instead
     */
    public function isEditor(): bool
    {
        return $this->role === UserRole::Editor;
    }

    /**
     * @deprecated Use AuthorizationService::can() with specific permissions instead
     */
    public function canManageContent(): bool
    {
        return $this->role?->canManageContent() ?? false;
    }

    /**
     * @deprecated Use AuthorizationService::can() with 'users.*' permissions instead
     */
    public function canManageUsers(): bool
    {
        return $this->role?->canManageUsers() ?? false;
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
     * Anonymize user data for GDPR compliance.
     * This action is irreversible.
     *
     * @deprecated Use UserServiceInterface::anonymize() instead for proper service injection.
     *             This method is kept for backwards compatibility.
     */
    public function anonymize(?string $anonymousName = null): void
    {
        // If no name provided, use default (for backwards compatibility)
        if ($anonymousName === null) {
            $anonymousName = 'Anónimo';
        }

        // Delete avatar from Cloudinary if exists
        if ($this->avatar_public_id !== null) {
            $this->deleteFromCloudinary($this->avatar_public_id);
        }

        $this->update([
            'name' => $anonymousName,
            'display_name' => null,
            'email' => 'anonymized_'.$this->id.'@anonymous.local',
            'pending_email' => null,
            'password' => Hash::make(Str::random(32)),
            'avatar_public_id' => null,
            'anonymized_at' => now(),
        ]);

        // Remove all roles
        $this->roles()->detach();
    }

    /**
     * Check if user has been anonymized.
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
