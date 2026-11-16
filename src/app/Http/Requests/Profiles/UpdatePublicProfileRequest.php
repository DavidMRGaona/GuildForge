<?php

declare(strict_types=1);

namespace App\Http\Requests\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\DTOs\ProfileLayoutOptionDTO;
use App\Application\Profiles\DTOs\ProfileUpdateDTO;
use App\Application\Profiles\Services\ProfileLayoutOptionsInterface;
use App\Domain\Profiles\Exceptions\InvalidProfileHandleException;
use App\Domain\Profiles\ProfileBioText;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Domain\Profiles\ValueObjects\ProfileLayout;
use App\Domain\Profiles\ValueObjects\ProfileLayoutEntry;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * `PUT /miembros/{handle}` (core#743): everything the editor saves, in one request, with an error per
 * field. Only sections and stats offered now are accepted; the badge only from an association member.
 * Whether the address is free and the 30 days are the service's, under a lock.
 */
final class UpdatePublicProfileRequest extends FormRequest
{
    use OwnsProfile;

    /** A handle written with spaces around it is still checked; anything longer is not one */
    private const int HANDLE_INPUT_MAX = 60;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $options = app(ProfileLayoutOptionsInterface::class);

        return [
            'handle' => ['required', 'string', 'max:'.self::HANDLE_INPUT_MAX, $this->handleRule()],
            'visibility' => ['required', Rule::enum(ProfileVisibility::class)],
            'bio' => ['nullable', 'string', 'max:'.ProfileBioText::MAX_LENGTH],
            'show_membership' => ['required', 'boolean'],
            ...$this->layoutRules('sections', $options->sections()),
            ...$this->layoutRules('stats', $options->stats()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'handle' => __('profiles.editor.attributes.handle'),
            'visibility' => __('profiles.editor.attributes.visibility'),
            'bio' => __('profiles.editor.attributes.bio'),
            'show_membership' => __('profiles.editor.attributes.show_membership'),
            'sections' => __('profiles.editor.attributes.sections'),
            'sections.*.key' => __('profiles.editor.attributes.section'),
            'sections.*.enabled' => __('profiles.editor.attributes.section'),
            'stats' => __('profiles.editor.attributes.stats'),
            'stats.*.key' => __('profiles.editor.attributes.stat'),
            'stats.*.enabled' => __('profiles.editor.attributes.stat'),
        ];
    }

    /**
     * @return array<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('show_membership') && ! $this->ownerIsAssociationMember()) {
                    $validator->errors()->add('show_membership', __('profiles.editor.errors.membership_only'));
                }
            },
        ];
    }

    public function toDto(): ProfileUpdateDTO
    {
        $options = app(ProfileLayoutOptionsInterface::class);
        $bio = $this->validated('bio');

        return new ProfileUpdateDTO(
            handle: (string) $this->validated('handle'),
            visibility: ProfileVisibility::from((string) $this->validated('visibility')),
            bio: is_string($bio) && $bio !== '' ? $bio : null,
            showMembership: $this->boolean('show_membership'),
            sections: $this->entries('sections'),
            stats: $this->entries('stats'),
            offeredSections: $this->keysOf($options->sections()),
            offeredStats: $this->keysOf($options->stats()),
        );
    }

    /**
     * The format of a new address here, with a field error; a reserved word gets the answer of an address
     * somebody holds.
     */
    private function handleRule(): Closure
    {
        // The address in the route is the owner's current one (OwnsProfile): kept as it is, never checked again
        $current = $this->route('handle');

        return static function (string $attribute, mixed $value, Closure $fail) use ($current): void {
            if (! is_string($value) || ProfileHandle::normalise($value) === $current) {
                return;
            }

            try {
                ProfileHandle::fromString($value);
            } catch (InvalidProfileHandleException $exception) {
                $fail($exception->reserved ? __('profiles.editor.errors.handle_unavailable') : __('profiles.editor.errors.handle_malformed'));
            }
        };
    }

    /**
     * @param  list<ProfileLayoutOptionDTO>  $offered
     * @return array<string, mixed>
     */
    private function layoutRules(string $field, array $offered): array
    {
        return [
            $field => ['present', 'array', 'list', 'max:'.ProfileLayout::MAX_ENTRIES],
            $field.'.*' => ['array:key,enabled'],
            $field.'.*.key' => ['required', 'string', 'distinct', Rule::in($this->keysOf($offered))],
            $field.'.*.enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @param  list<ProfileLayoutOptionDTO>  $options
     * @return list<string>
     */
    private function keysOf(array $options): array
    {
        return array_map(static fn (ProfileLayoutOptionDTO $option): string => $option->key, $options);
    }

    /**
     * @return list<ProfileLayoutEntry>
     */
    private function entries(string $field): array
    {
        $entries = [];

        foreach ((array) $this->validated($field, []) as $entry) {
            if (is_array($entry) && is_string($entry['key'] ?? null)) {
                $entries[] = new ProfileLayoutEntry($entry['key'], filter_var($entry['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN));
            }
        }

        return $entries;
    }

    /**
     * Fails closed: a check that throws refuses the badge.
     */
    private function ownerIsAssociationMember(): bool
    {
        $user = $this->user();

        try {
            return $user instanceof Authenticatable && app(AssociationMembershipServiceInterface::class)->isAssociationMember($user);
        } catch (Throwable $exception) {
            app(LoggerInterface::class)->warning('[Profiles] The membership of a profile owner could not be checked; the badge is refused.', [
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
