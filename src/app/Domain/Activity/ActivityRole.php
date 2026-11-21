<?php

declare(strict_types=1);

namespace App\Domain\Activity;

/**
 * What a person did in a subject (core#774): played it, ran it, organized it… One person may hold
 * several roles in the same subject (a director who also played), each a fact of its own. A new
 * role is added here when a module needs one; existing values never change, since consumers store them.
 */
enum ActivityRole: string
{
    case Player = 'player';
    case Director = 'director';
    case Organizer = 'organizer';
    case Creator = 'creator';
    case Attendee = 'attendee';
    case Author = 'author';
    case Respondent = 'respondent';
    case Booker = 'booker';
    case Member = 'member';
    case Proposer = 'proposer';
    case Owner = 'owner';
}
