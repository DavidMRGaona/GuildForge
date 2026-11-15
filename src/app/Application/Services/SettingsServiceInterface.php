<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\LocationSettingsDTO;
use DateTimeImmutable;

interface SettingsServiceInterface
{
    /**
     * Get a single setting by key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set or update a setting.
     */
    public function set(string $key, string $value): void;

    /**
     * Get all location settings.
     */
    public function getLocationSettings(): LocationSettingsDTO;

    /**
     * Get an encrypted setting, decrypting it on read.
     */
    public function getEncrypted(string $key, mixed $default = null): mixed;

    /**
     * Set a setting, encrypting the value before storage.
     */
    public function setEncrypted(string $key, string $value): void;

    /**
     * Read a setting from the table, never from the cache. get() may lag behind a write made
     * by another request; code that decides a write on what it reads (compare and set, a
     * background check storing its result) reads with this.
     */
    public function getStored(string $key, mixed $default = null): mixed;

    /**
     * Read an encrypted setting from the table, never from the cache, decrypting it.
     * Missing, empty or undecryptable values return the default.
     */
    public function getStoredEncrypted(string $key, mixed $default = null): mixed;

    /**
     * Invalidate the settings cache once the current transaction commits (at once outside
     * one). Call it after writing the settings table without set().
     */
    public function clearCache(): void;

    /**
     * Check if public registration is enabled.
     */
    public function isRegistrationEnabled(): bool;

    /**
     * Check if public login is enabled.
     */
    public function isLoginEnabled(): bool;

    /**
     * Check if email verification is required.
     */
    public function isEmailVerificationRequired(): bool;

    /**
     * When the required email verification started to be enforced (recorded once by a
     * migration): accounts created earlier are exempt. Null when missing or malformed.
     */
    public function getEmailVerificationEnforcedSince(): ?DateTimeImmutable;

    /**
     * Check if the public site is in maintenance mode.
     */
    public function isMaintenanceModeEnabled(): bool;

    /**
     * Get the message shown to visitors during maintenance, if any.
     */
    public function getMaintenanceMessage(): ?string;
}
