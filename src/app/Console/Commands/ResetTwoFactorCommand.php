<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Console\Concerns\ReadsTypedInput;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Takes away an account's two-factor authentication from the console (core#771): the way back in when
 * the only administrator loses their phone and their recovery codes, since the panel's action is
 * for the administration and this account has nobody above it. Does what the panel does (sessions
 * closed, the person mailed) and asks first unless `--force`.
 */
final class ResetTwoFactorCommand extends Command
{
    use ReadsTypedInput;

    protected $signature = 'users:reset-two-factor
                            {email : Email of the account}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Remove the two-factor authentication of an account that lost its phone and recovery codes';

    public function handle(TwoFactorResetInterface $reset): int
    {
        $email = mb_strtolower(trim($this->stringArgument('email')));

        // Deactivated accounts too: reactivating one must not bring its second factor back
        $user = $email === '' ? null : UserModel::query()->withTrashed()->whereRaw('lower(email) = ?', [$email])->first();

        if (! $user instanceof UserModel) {
            $this->components->error((string) __('two_factor.command.not_found'));

            return self::FAILURE;
        }

        if (! $this->flag('force') && ! $this->components->confirm((string) __('two_factor.command.confirm', ['email' => $email]))) {
            $this->components->warn((string) __('two_factor.command.cancelled'));

            return self::FAILURE;
        }

        try {
            $done = $reset->reset((string) $user->getKey(), null);
        } catch (TwoFactorException) {
            $this->components->error((string) __('two_factor.command.refused'));

            return self::FAILURE;
        }

        if (! $done) {
            $this->components->info((string) __('two_factor.command.nothing', ['email' => $email]));

            return self::SUCCESS;
        }

        Log::info('Two-factor reset from the command', [
            'source' => 'command',
            'user_id' => (string) $user->getKey(),
        ]);

        $this->components->info((string) __('two_factor.command.done', ['email' => $email]));

        return self::SUCCESS;
    }
}
