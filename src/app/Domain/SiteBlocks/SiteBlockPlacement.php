<?php

declare(strict_types=1);

namespace App\Domain\SiteBlocks;

/**
 * Where on the public site a block may be placed (core#696).
 */
enum SiteBlockPlacement: string
{
    /** A column of the configurable footer */
    case Footer = 'footer';

    /** Inside a popup window of the announcements module */
    case Popup = 'popup';
}
