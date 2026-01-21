<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\CreateUserDTO;
use App\Application\DTOs\Response\UserResponseDTO;
use App\Application\DTOs\UpdateUserDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Domain\Enums\UserRole;
use App\Domain\Events\UserLoggedIn;
use App\Domain\Events\UserLoggedOut;
use App\Domain\Events\UserPasswordChanged;
use App\Domain\Events\UserProfileUpdated;
use App\Domain\Events\UserRegistered;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\VerifyPendingEmailNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

final readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
    ) {
    }

    public function register(CreateUserDTO $dto): UserResponseDTO
    {
        $user = UserModel::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
            'role' => UserRole::Member,
        ]);

        event(new Registered($user));
        Event::dispatch(new UserRegistered((string) $user->id, $user->email));

        // Send welcome email
        $appName = (string) config('app.name', 'Runesword');
        $user->notify(new WelcomeNotification($appName));

        return $this->dtoFactory->createUserDTO($user);
    }

    public function attemptLogin(string $email, string $password, bool $remember = false): bool
    {
        $result = Auth::attempt([
            'email' => $email,
            'password' => $password,
        ], $remember);

        if ($result) {
            /** @var UserModel $user */
            $user = Auth::user();
            Event::dispatch(new UserLoggedIn((string) $user->id, $user->email));
        }

        return $result;
    }

    public function logout(): void
    {
        /** @var UserModel|null $user */
        $user = Auth::user();

        if ($user !== null) {
            Event::dispatch(new UserLoggedOut((string) $user->id));
        }

        Auth::guard('web')->logout();
    }

    public function getCurrentUser(): ?UserResponseDTO
    {
        /** @var UserModel|null $user */
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        return $this->dtoFactory->createUserDTO($user);
    }

    public function sendPasswordResetLink(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(string $token, string $email, string $password): void
    {
        Password::reset(
            [
                'email' => $email,
                'password' => $password,
                'token' => $token,
            ],
            static function (UserModel $user, string $password): void {
                $user->update([
                    'password' => $password,
                ]);
            }
        );
    }

    public function sendEmailVerificationNotification(string $userId): void
    {
        $user = UserModel::findOrFail($userId);
        $user->sendEmailVerificationNotification();
    }

    public function verifyEmail(string $userId, string $hash): bool
    {
        $user = UserModel::findOrFail($userId);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return false;
        }

        if ($user->hasVerifiedEmail()) {
            return true;
        }

        $user->markEmailAsVerified();

        return true;
    }

    public function updateProfile(string $userId, UpdateUserDTO $dto): UserResponseDTO
    {
        $user = UserModel::findOrFail($userId);

        // Handle avatar cleanup if avatar is being changed
        if ($dto->avatarPublicId !== null && $user->avatar_public_id !== null && $user->avatar_public_id !== $dto->avatarPublicId) {
            Storage::disk('images')->delete($user->avatar_public_id);
        }

        // Check if email is being changed
        $emailIsChanging = $dto->email !== $user->email;
        $pendingEmail = $emailIsChanging ? $dto->email : null;

        $user->update([
            'name' => $dto->name,
            'display_name' => $dto->displayName,
            'pending_email' => $pendingEmail,
            'avatar_public_id' => $dto->avatarPublicId ?? $user->avatar_public_id,
        ]);

        // Send verification email if email is changing
        if ($emailIsChanging) {
            $user->notify(new VerifyPendingEmailNotification($dto->email));
        }

        Event::dispatch(new UserProfileUpdated((string) $user->id));

        /** @var UserModel $freshUser */
        $freshUser = $user->fresh();

        return $this->dtoFactory->createUserDTO($freshUser);
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        $user = UserModel::findOrFail($userId);

        if (!Hash::check($currentPassword, $user->password)) {
            return false;
        }

        $user->update([
            'password' => $newPassword,
        ]);

        Event::dispatch(new UserPasswordChanged((string) $user->id));

        return true;
    }

    public function verifyPendingEmail(string $userId, string $hash): bool
    {
        $user = UserModel::findOrFail($userId);

        if ($user->pending_email === null) {
            return false;
        }

        if (!hash_equals(sha1($user->pending_email), $hash)) {
            return false;
        }

        $user->update([
            'email' => $user->pending_email,
            'pending_email' => null,
        ]);

        return true;
    }
}
