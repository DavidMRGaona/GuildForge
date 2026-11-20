<?php

declare(strict_types=1);

namespace App\Console\Commands\WebPush;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Prints a new VAPID key pair for this installation (core#803), to copy into its environment.
 * Never writes the environment itself, and every installation needs its own pair: changing it
 * later drops every browser that subscribed with the old one.
 */
final class WebPushKeysCommand extends Command
{
    protected $signature = 'web-push:keys';

    protected $description = 'Print a new VAPID key pair for browser push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('WEB_PUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEB_PUSH_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('WEB_PUSH_SUBJECT=mailto:');
        $this->newLine();
        $this->comment('Complete WEB_PUSH_SUBJECT with a contact address (mailto: or https:). Keep the private key secret.');

        return self::SUCCESS;
    }
}
