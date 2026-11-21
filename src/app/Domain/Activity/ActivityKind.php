<?php

declare(strict_types=1);

namespace App\Domain\Activity;

/**
 * What kind of thing an activity source reports (core#774): every subject of a source is of the
 * source's kind. A new kind is added here when a module needs one; existing values never change,
 * since consumers store them.
 */
enum ActivityKind: string
{
    case GameSession = 'game_session';
    case Campaign = 'campaign';
    case Tournament = 'tournament';
    case Event = 'event';
    case ForumPost = 'forum_post';
    case ForumDiscussion = 'forum_discussion';
    case MembershipYear = 'membership_year';
    case SurveyResponse = 'survey_response';
    case VenueBooking = 'venue_booking';
    case Account = 'account';
    case ProfileCompleted = 'profile_completed';
    case Proposal = 'proposal';
}
