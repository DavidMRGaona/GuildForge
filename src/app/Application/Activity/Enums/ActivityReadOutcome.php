<?php

declare(strict_types=1);

namespace App\Application\Activity\Enums;

/**
 * How reading an activity source went (core#774). Only `Read` is an answer: `Missing` (nobody offers
 * the type now: its module is disabled or gone) and `Failed` (the source threw or broke the
 * contract, warned once a day) both mean «unknown», so a consumer keeps what it already had.
 */
enum ActivityReadOutcome: string
{
    case Read = 'read';
    case Missing = 'missing';
    case Failed = 'failed';
}
