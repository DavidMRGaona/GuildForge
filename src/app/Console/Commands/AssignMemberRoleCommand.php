<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Domain\Authorization\Exceptions\RoleNotFoundException;
use Illuminate\Console\Command;

/**
 * Gives "member", the base role of every account, to the accounts created before new
 * accounts got it. Idempotent: it never removes a role and skips users that have it.
 */
final class AssignMemberRoleCommand extends Command
{
    protected $signature = 'users:assign-member-role
                            {--dry-run : Count the users that would get the role without changing anything}
                            {--chunk=500 : Users handled per batch}';

    protected $description = 'Give the member role to every user that does not have it';

    public function handle(MemberRoleServiceInterface $memberRole): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        try {
            $count = $memberRole->assignToAllUsers($dryRun, $chunk);
        } catch (RoleNotFoundException) {
            $this->components->error((string) __('authorization.member_role.missing'));

            return self::FAILURE;
        }

        $this->components->info(trans_choice(
            $dryRun ? 'authorization.member_role.would_assign' : 'authorization.member_role.assigned',
            $count,
            ['count' => $count],
        ));

        return self::SUCCESS;
    }
}
