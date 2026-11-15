<?php

declare(strict_types=1);

namespace App\Console\Commands\Profiles;

use App\Application\Profiles\Services\UserProfileServiceInterface;
use Illuminate\Console\Command;

/**
 * Creates the public profile row of every account without one (core#740): accounts older than profiles
 * and any whose row could not be created with it. Idempotent; scheduled daily.
 */
final class CreateMissingProfilesCommand extends Command
{
    protected $signature = 'profiles:create-missing
                            {--dry-run : Count the profiles that would be created without changing anything}
                            {--chunk=500 : Accounts handled per batch}';

    protected $description = 'Create the public profile of every account that has none';

    public function handle(UserProfileServiceInterface $profiles): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $result = $profiles->createMissing($dryRun, $chunk);

        $this->components->info(trans_choice(
            $dryRun ? 'profiles.create_missing.would_create' : 'profiles.create_missing.created',
            $result->created,
            ['count' => $result->created],
        ));

        if ($result->failed > 0) {
            $this->components->error(trans_choice('profiles.create_missing.failed', $result->failed, ['count' => $result->failed]));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
