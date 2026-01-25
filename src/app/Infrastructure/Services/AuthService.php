<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\CreateUserDTO;
use App\Application\DTOs\ImageOptimizationSettingsDTO;
use App\Application\DTOs\Response\UserResponseDTO;
use App\Application\DTOs\UpdateUserDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Domain\Events\UserLoggedIn;
use App\Domain\Events\UserLoggedOut;
use App\Domain\Events\UserPasswordChanged;
use App\Domain\Events\UserProfileUpdated;
use App\Domain\Events\UserRegistered;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\VerifyPendingEmailNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
        private ImageOptimizationServiceInterface $imageOptimizer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function register(CreateUserDTO $dto): UserResponseDTO
    {
        $user = $this->userRepository->create($dto);
        $userModel = $this->userRepository->findModelById($user->id());

        if ($userModel === null) {
            throw new \RuntimeException('Failed to retrieve user after creation');
        }

        event(new Registered($userModel));
        Event::dispatch(new UserRegistered($user->id()->value, $user->email()));

        // Send welcome email
        $appName = (string) config('app.name', 'GuildForge');
        $userModel->notify(new WelcomeNotification($appName));

        return $this->dtoFactory->createUserDTO($userModel);
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
        $userModel = $this->userRepository->findModelById(UserId::fromString($userId));
        if ($userModel === null) {
            throw new ModelNotFoundException("User not found: $userId");
        }
        $userModel->sendEmailVerificationNotification();
    }

    public function verifyEmail(string $userId, string $hash): bool
    {
        $userModel = $this->userRepository->findModelById(UserId::fromString($userId));
        if ($userModel === null) {
            throw new ModelNotFoundException("User not found: $userId");
        }

        if (! hash_equals(sha1($userModel->getEmailForVerification()), $hash)) {
            return false;
        }

        if ($userModel->hasVerifiedEmail()) {
            return true;
        }

        $userModel->markEmailAsVerified();

        return true;
    }

    public function updateProfile(string $userId, UpdateUserDTO $dto): UserResponseDTO
    {
        $userModel = $this->userRepository->findModelById(UserId::fromString($userId));
        if ($userModel === null) {
            throw new ModelNotFoundException("User not found: $userId");
        }

        // Handle avatar cleanup if avatar is being changed
        if ($dto->avatarPublicId !== null && $userModel->avatar_public_id !== null && $userModel->avatar_public_id !== $dto->avatarPublicId) {
            Storage::disk('images')->delete($userModel->avatar_public_id);
        }

        // Check if email is being changed
        $emailIsChanging = $dto->email !== $userModel->email;
        $pendingEmail = $emailIsChanging ? $dto->email : null;

        $userModel->update([
            'name' => $dto->name,
            'display_name' => $dto->displayName,
            'pending_email' => $pendingEmail,
            'avatar_public_id' => $dto->avatarPublicId ?? $userModel->avatar_public_id,
        ]);

        // Send verification email if email is changing
        if ($emailIsChanging) {
            $userModel->notify(new VerifyPendingEmailNotification($dto->email));
        }

        Event::dispatch(new UserProfileUpdated((string) $userModel->id));

        /** @var UserModel $freshUser */
        $freshUser = $userModel->fresh();

        return $this->dtoFactory->createUserDTO($freshUser);
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        $userModel = $this->userRepository->findModelById(UserId::fromString($userId));
        if ($userModel === null) {
            throw new ModelNotFoundException("User not found: $userId");
        }

        if (! Hash::check($currentPassword, $userModel->password)) {
            return false;
        }

        $userModel->update([
            'password' => $newPassword,
        ]);

        Event::dispatch(new UserPasswordChanged((string) $userModel->id));

        return true;
    }

    public function verifyPendingEmail(string $userId, string $hash): bool
    {
        $userModel = $this->userRepository->findModelById(UserId::fromString($userId));
        if ($userModel === null) {
            throw new ModelNotFoundException("User not found: $userId");
        }

        if ($userModel->pending_email === null) {
            return false;
        }

        if (! hash_equals(sha1($userModel->pending_email), $hash)) {
            return false;
        }

        $userModel->update([
            'email' => $userModel->pending_email,
            'pending_email' => null,
        ]);

        return true;
    }

    public function uploadAvatar(string $userId, string $contents, string $mimeType): ?string
    {
        $avatarSettings = ImageOptimizationSettingsDTO::withOverrides([
            'maxWidth' => 512,
            'maxHeight' => 512,
            'quality' => 90,
            'minSizeBytes' => 0, // Always optimize avatars
        ]);

        $optimizedContents = $this->imageOptimizer->optimize(
            $contents,
            $mimeType,
            $avatarSettings
        );

        $path = 'users/'.$userId;
        $filename = Str::uuid()->toString().'.jpg';
        $fullPath = $path.'/'.$filename;

        $stored = Storage::disk('images')->put($fullPath, $optimizedContents);

        return $stored !== false ? $fullPath : null;
    }
}
