<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Realtime\Services\RealtimeConfigurationInterface;
use Illuminate\Console\Command;

/**
 * Whether this site has real time on (core#493), with the same rule as the application, so the
 * production image starts the Reverb server only when it is: exit 0 when on, 1 when off.
 */
final class RealtimeStatusCommand extends Command
{
    protected $signature = 'realtime:status';

    protected $description = 'Tell whether real time is on (exit 0) or off (exit 1) for this site';

    public function handle(RealtimeConfigurationInterface $configuration): int
    {
        $origin = $configuration->connectSource();

        if ($origin === null) {
            $this->line('Real time is off: BROADCAST_CONNECTION is not reverb or a REVERB_* setting is missing or invalid.');

            return self::FAILURE;
        }

        $this->line("Real time is on: browsers connect to {$origin}.");

        return self::SUCCESS;
    }
}
