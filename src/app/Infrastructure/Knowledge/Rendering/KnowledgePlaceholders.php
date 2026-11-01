<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;

/**
 * Current values of the markers a guide body can use (`{{site_name}}`, `{{association_role}}`).
 */
final readonly class KnowledgePlaceholders
{
    /** Marker names a guide body may use. */
    public const array NAMES = ['site_name', 'association_role'];

    /** Delimiters-free pattern of a well-formed marker, shared with the renderer. */
    public const string MARKER_PATTERN = '\{\{([a-z_]+)\}\}';

    public function __construct(
        private SiteBrandingServiceInterface $branding,
        private AssociationMembershipServiceInterface $membership,
    ) {}

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return [
            'site_name' => $this->branding->siteName(),
            'association_role' => $this->associationRole(),
        ];
    }

    private function associationRole(): string
    {
        $name = $this->membership->roleName();
        $displayName = $name === null
            ? null
            : RoleModel::query()->where('name', $name)->value('display_name');

        return is_string($displayName) && trim($displayName) !== ''
            ? $displayName
            : __('knowledge.placeholders.association_role_fallback');
    }
}
