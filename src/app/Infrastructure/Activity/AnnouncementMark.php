<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity;

/**
 * Whether a subject was already announced in an outermost transaction (core#774): shared by every
 * commit callback the publisher registers for it, so the first to run announces it and the rest skip.
 */
final class AnnouncementMark
{
    public bool $done = false;
}
