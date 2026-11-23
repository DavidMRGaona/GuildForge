<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Enums;

/**
 * What can be proposed from the web. The value is the permission resource of the type.
 */
enum ProposalType: string
{
    case Event = 'events';
    case Article = 'articles';

    /** Subfolder of each type's folder that holds the images of its web form, with its trailing slash */
    public const string IMAGE_SUBFOLDER = 'proposals/';

    /**
     * Where the web form of the type stores its images (e.g. `events/proposals/`), apart from the panel's
     * dated folders so the media library can conceal the unused ones (core#841).
     */
    public function imageFolder(): string
    {
        return $this->value.'/'.self::IMAGE_SUBFOLDER;
    }

    /** Needed to propose when the access level is the permission */
    public function proposePermission(): string
    {
        return $this->value.'.propose';
    }

    /** Needed to approve, return or reject */
    public function reviewPermission(): string
    {
        return $this->value.'.review';
    }

    /** Submitting is approving for whoever holds it */
    public function publishDirectlyPermission(): string
    {
        return $this->value.'.publish_proposals_directly';
    }
}
