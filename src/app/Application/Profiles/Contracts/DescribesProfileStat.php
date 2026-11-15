<?php

declare(strict_types=1);

namespace App\Application\Profiles\Contracts;

/**
 * Optional for a ProfileStatInterface (core#743): one line the profile editor shows under the stat's
 * label, saying what it counts. Apart from ProfileStatInterface, so the stats written for the contract
 * published with core#742 keep working unchanged; a stat without it shows only its label.
 */
interface DescribesProfileStat
{
    /**
     * What the stat counts, already translated.
     */
    public function description(): string;
}
