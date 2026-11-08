<?php

declare(strict_types=1);

namespace App\Application\QrCodes\Enums;

/**
 * How much of the code may be damaged or covered and still be read: about 7, 15, 25 and 30 %.
 * Higher levels need more modules, so the same size draws smaller blocks.
 */
enum QrCodeErrorCorrection: string
{
    case Low = 'low';
    case Medium = 'medium';
    case Quartile = 'quartile';
    case High = 'high';
}
