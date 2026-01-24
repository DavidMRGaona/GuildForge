<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $display_name
 * @property string $email
 * @property string|null $pending_email
 * @property string $password
 * @property string|null $avatar_public_id
 * @property UserRole $role
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class UserModel extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use DeletesCloudinaryImages;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

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
        'name',
        'display_name',
        'email',
        'pending_email',
        'password',
        'avatar_public_id',
        'role',
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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role->canAccessPanel();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isEditor(): bool
    {
        return $this->role === UserRole::Editor;
    }

    public function canManageContent(): bool
    {
        return $this->role->canManageContent();
    }

    public function canManageUsers(): bool
    {
        return $this->role->canManageUsers();
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
