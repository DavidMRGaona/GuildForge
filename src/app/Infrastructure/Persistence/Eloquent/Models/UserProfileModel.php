<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileLayout;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JsonException;

/**
 * The public profile of one account (core#740). Written through UserProfileServiceInterface.
 *
 * @property string $user_id
 * @property string $handle
 * @property bool $handle_chosen
 * @property Carbon|null $handle_changed_at when the owner last chose the address (core#743)
 * @property ProfileVisibility $visibility
 * @property string|null $bio
 * @property string|null $banner_public_id
 * @property bool $show_membership
 * @property ProfileLayout $layout
 * @property Carbon|null $completed_at when it became complete: public name, avatar and «Sobre mí» (core#777)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class UserProfileModel extends Model
{
    use StoresInstantsInStorageTimezone;

    protected $table = 'user_profiles';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'handle',
        'handle_chosen',
        'handle_changed_at',
        'visibility',
        'bio',
        'banner_public_id',
        'show_membership',
        'layout',
    ];

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handle_chosen' => 'boolean',
            'handle_changed_at' => 'datetime',
            'show_membership' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * An unknown stored value hides the profile.
     *
     * @return Attribute<ProfileVisibility, ProfileVisibility|string>
     */
    protected function visibility(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): ProfileVisibility => ProfileVisibility::fromStored($value),
            set: static fn (ProfileVisibility|string $value): string => $value instanceof ProfileVisibility ? $value->value : ProfileVisibility::fromStored($value)->value,
        );
    }

    /**
     * Read tolerantly: anything unreadable is the default layout.
     *
     * @return Attribute<ProfileLayout, ProfileLayout|null>
     */
    protected function layout(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value): ProfileLayout {
                if (! is_string($value) || $value === '') {
                    return ProfileLayout::empty();
                }

                try {
                    return ProfileLayout::fromArray(json_decode($value, true, 8, JSON_THROW_ON_ERROR));
                } catch (JsonException) {
                    return ProfileLayout::empty();
                }
            },
            set: static fn (?ProfileLayout $value): ?string => ! $value instanceof ProfileLayout || $value->isEmpty()
                ? null
                : json_encode($value->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }
}
