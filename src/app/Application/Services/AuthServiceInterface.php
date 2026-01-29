<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CreateUserDTO;
use App\Application\DTOs\Response\UserResponseDTO;
use App\Application\DTOs\UpdateUserDTO;

interface AuthServiceInterface
{
    /**
     * Register a new user.
     */
    public function register(CreateUserDTO $dto): UserResponseDTO;

    /**
     * Attempt to log in a user.
     */
    public function attemptLogin(string $email, string $password, bool $remember = false): bool;

    /**
     * Log out the current user.
     */
    public function logout(): void;

    /**
     * Get the currently authenticated user.
     */
    public function getCurrentUser(): ?UserResponseDTO;

    /**
     * Send a password reset link to the user.
     */
    public function sendPasswordResetLink(string $email): void;

    /**
     * Reset the user's password.
     */
    public function resetPassword(string $token, string $email, string $password): void;

    /**
     * Send email verification notification.
     */
    public function sendEmailVerificationNotification(string $userId): void;

    /**
     * Verify the user's email address.
     */
    public function verifyEmail(string $userId, string $hash): bool;

    /**
     * Update the user's profile.
     */
    public function updateProfile(string $userId, UpdateUserDTO $dto): UserResponseDTO;

    /**
     * Change the user's password.
     */
    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool;

    /**
     * Verify and apply the pending email change.
     */
    public function verifyPendingEmail(string $userId, string $hash): bool;

    /**
     * Upload and optimize a user avatar.
     *
     * @param  string  $userId  The user ID
     * @param  string  $contents  The raw file contents
     * @param  string  $mimeType  The file MIME type
     * @return string|null The avatar public ID, or null if upload failed
     */
    public function uploadAvatar(string $userId, string $contents, string $mimeType): ?string;

    /**
     * Check if the user has verified their email address.
     */
    public function hasVerifiedEmail(string $userId): bool;
}
