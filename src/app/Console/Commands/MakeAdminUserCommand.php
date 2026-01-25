<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

final class MakeAdminUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user for Filament';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text(
            label: 'What is the username?',
            required: true,
            validate: fn (string $value) => UserModel::where('name', $value)->exists()
                ? 'A user with this username already exists.'
                : null
        );

        $email = text(
            label: 'What is the email address?',
            required: true,
            validate: function (string $value) {
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return 'Please enter a valid email address.';
                }
                if (UserModel::where('email', $value)->exists()) {
                    return 'A user with this email already exists.';
                }

                return null;
            }
        );

        $password = password(
            label: 'What is the password?',
            required: true,
            validate: fn (string $value) => strlen($value) < 8
                ? 'The password must be at least 8 characters.'
                : null
        );

        $user = UserModel::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'display_name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'role' => \App\Domain\Enums\UserRole::Admin,
        ]);

        // Attach admin role if it exists
        $adminRole = RoleModel::where('name', 'admin')->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole->id);
        }

        $this->components->info("Admin user [{$email}] created successfully.");

        return self::SUCCESS;
    }
}
