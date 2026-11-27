<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\DTOs\CreateUserDTO;
use App\Application\DTOs\ImageOptimizationSettingsDTO;
use App\Application\DTOs\LoginAttemptDTO;
use App\Application\DTOs\Response\UserResponseDTO;
use App\Application\DTOs\UpdateUserDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\UserModelQueryServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Domain\Events\RegistrationFieldsSubmitted;
use App\Domain\Events\UserLoggedIn;
use App\Domain\Events\UserLoggedOut;
use App\Domain\Events\UserPasswordChanged;
use App\Domain\Events\UserProfileUpdated;
use App\Domain\Events\UserRegistered;
use App\Domain\Exceptions\UserNotFoundException;
use App\Domain\Profiles\PublicName;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Images\AvatarPath;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\VerifyPendingEmailNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
        private ImageOptimizationServiceInterface $imageOptimizer,
        private UserRepositoryInterface $userRepository,
        private UserModelQueryServiceInterface $userModelQuery,
        private MemberRoleServiceInterface $memberRole,
        private SiteBrandingServiceInterface $siteBranding,
        private TwoFactorStatusReaderInterface $twoFactor,
        private AvatarPath $avatarPaths = new AvatarPath,
    ) {}

    public function register(CreateUserDTO $dto, array $registrationFields = []): UserResponseDTO
    {
        $user = $this->userRepository->create($dto);
        $userModel = $this->userModelQuery->findModelById($user->id());

        if (! $userModel instanceof UserModel) {
            throw new \RuntimeException('Failed to retrieve user after creation');
        }

        // Before the events: their listeners must see the account with its base role
        $this->memberRole->assignTo($userModel);

        event(new Registered($userModel));
        Event::dispatch(new UserRegistered($user->id()->value, $user->email()));
        $this->announceRegistrationFields($user->id()->value, $registrationFields);

        // Send welcome email
        $userModel->notify(new WelcomeNotification($this->siteBranding->siteName()));

        return $this->dtoFactory->createUserDTO($userModel);
    }

    public function attemptLogin(string $email, #[\SensitiveParameter] string $password, bool $remember = false): LoginAttemptDTO
    {
        $credentials = [
            'email' => strtolower($email),
            'password' => $password,
        ];
        $guard = Auth::guard('web');

        // validate() only checks: nothing is signed in and no «remember me» cookie is queued
        // before the second factor (core#770)
        if (! $guard->validate($credentials)) {
            return LoginAttemptDTO::failed();
        }

        $user = $guard->getLastAttempted();

        if (! $user instanceof UserModel) {
            return LoginAttemptDTO::failed();
        }

        $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);
        $userId = (string) $user->id;

        if ($this->twoFactor->isEnabled($userId)) {
            return LoginAttemptDTO::twoFactorRequired($userId);
        }

        $guard->login($user, $remember);
        Event::dispatch(new UserLoggedIn($userId, $user->email));

        return LoginAttemptDTO::signedIn($userId);
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

    public function resetPassword(string $token, string $email, string $password): bool
    {
        $status = Password::reset(
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

        return $status === Password::PASSWORD_RESET;
    }

    public function sendEmailVerificationNotification(string $userId): void
    {
        $userModel = $this->getUserModelOrFail($userId);
        $userModel->sendEmailVerificationNotification();
    }

    public function sendPendingEmailVerificationNotification(string $userId): bool
    {
        $userModel = $this->getUserModelOrFail($userId);

        if ($userModel->pending_email === null) {
            return false;
        }

        $userModel->notify(new VerifyPendingEmailNotification($userModel->pending_email));

        return true;
    }

    public function verifyEmail(string $userId, string $hash): bool
    {
        $userModel = $this->getUserModelOrFail($userId);

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
        $userModel = $this->getUserModelOrFail($userId);

        if ($dto->avatarPublicId !== null) {
            $this->deleteReplacedAvatar($userModel, $dto->avatarPublicId);
        }

        // The current email cancels a pending change; the pending one keeps it without a new link
        $requestsNewEmail = $dto->email !== $userModel->email && $dto->email !== $userModel->pending_email;
        $pendingEmail = $dto->email === $userModel->email ? null : $dto->email;

        $userModel->update([
            'name' => $dto->name,
            'display_name' => $dto->displayName,
            'pending_email' => $pendingEmail,
            'avatar_public_id' => $dto->avatarPublicId ?? $userModel->avatar_public_id,
        ]);

        if ($requestsNewEmail) {
            $userModel->notify(new VerifyPendingEmailNotification($dto->email));
        }

        Event::dispatch(new UserProfileUpdated((string) $userModel->id));

        /** @var UserModel $freshUser */
        $freshUser = $userModel->fresh();

        return $this->dtoFactory->createUserDTO($freshUser);
    }

    public function choosePublicName(string $userId, string $publicName): bool
    {
        $chosen = DB::transaction(function () use ($userId, $publicName): bool {
            // Locked: two tabs sending the screen at once must not both write it
            $userModel = UserModel::query()->whereKey($userId)->lockForUpdate()->first();

            if (! $userModel instanceof UserModel) {
                throw UserNotFoundException::withId($userId);
            }

            if (! PublicName::isMissing($userModel->display_name)) {
                return false;
            }

            // Through the model: UserProfileObserver moves the profile address to follow it
            $userModel->update(['display_name' => $publicName]);

            return true;
        });

        if ($chosen) {
            Event::dispatch(new UserProfileUpdated($userId));
        }

        return $chosen;
    }

    public function updateAvatar(string $userId, string $avatarPublicId): UserResponseDTO
    {
        $userModel = $this->getUserModelOrFail($userId);

        $this->deleteReplacedAvatar($userModel, $avatarPublicId);

        $userModel->update(['avatar_public_id' => $avatarPublicId]);

        Event::dispatch(new UserProfileUpdated((string) $userModel->id));

        /** @var UserModel $freshUser */
        $freshUser = $userModel->fresh();

        return $this->dtoFactory->createUserDTO($freshUser);
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        $userModel = $this->getUserModelOrFail($userId);

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
        $userModel = $this->getUserModelOrFail($userId);

        if ($userModel->pending_email === null) {
            return false;
        }

        if (! hash_equals(sha1($userModel->pending_email), $hash)) {
            return false;
        }

        // The signed link reached the new address, so it is verified as well. Not mass
        // assignable on purpose: forceFill, like markEmailAsVerified()
        $userModel->forceFill([
            'email' => $userModel->pending_email,
            'pending_email' => null,
            'email_verified_at' => $userModel->email_verified_at ?? now(),
        ])->save();

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

        // Named after the stored bytes; animated avatars are marked for animated delivery
        $fullPath = $this->avatarPaths->forUpload('users/'.$userId, $optimizedContents);

        $stored = Storage::disk('images')->put($fullPath, $optimizedContents);

        return $stored !== false ? $fullPath : null;
    }

    public function uploadAvatarFromFile(string $userId, UploadedFile $file): ?string
    {
        return $this->uploadAvatar(
            $userId,
            $file->getContent(),
            $file->getMimeType() ?? 'image/jpeg'
        );
    }

    public function hasVerifiedEmail(string $userId): bool
    {
        $userModel = $this->userModelQuery->findModelById(UserId::fromString($userId));

        return $userModel instanceof UserModel && $userModel->hasVerifiedEmail();
    }

    /**
     * Module fields of the form (core#699). Listeners are the modules' and should be queued; one
     * that throws is logged without personal data and never undoes nor stops the registration.
     *
     * @param  array<string, bool>  $values
     */
    private function announceRegistrationFields(string $userId, array $values): void
    {
        if ($values === []) {
            return;
        }

        try {
            Event::dispatch(new RegistrationFieldsSubmitted($userId, $values));
        } catch (Throwable $exception) {
            Log::error('[AuthService] A RegistrationFieldsSubmitted listener failed', [
                'userId' => $userId,
                'fields' => array_keys($values),
                'exception' => $exception::class,
            ]);
        }
    }

    private function deleteReplacedAvatar(UserModel $userModel, string $newAvatarPublicId): void
    {
        if ($userModel->avatar_public_id !== null && $userModel->avatar_public_id !== $newAvatarPublicId) {
            Storage::disk('images')->delete($userModel->avatar_public_id);
        }
    }

    /**
     * @throws UserNotFoundException
     */
    private function getUserModelOrFail(string $userId): UserModel
    {
        $userModel = $this->userModelQuery->findModelById(
            UserId::fromString($userId)
        );

        if (! $userModel instanceof UserModel) {
            throw UserNotFoundException::withId($userId);
        }

        return $userModel;
    }
}
