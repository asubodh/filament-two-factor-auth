<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface TwoFactorAuthenticatable
{
    /**
     * Get the user's two-factor secret key.
     */
    public function getTwoFactorSecret(): ?string;

    /**
     * Set the user's two-factor secret key.
     */
    public function setTwoFactorSecret(?string $secret): void;

    /**
     * Determine if the user has two-factor authentication enabled.
     */
    public function hasTwoFactorEnabled(): bool;

    /**
     * Enable two-factor authentication for the user.
     */
    public function enableTwoFactor(string $secret): void;

    /**
     * Disable two-factor authentication for the user.
     */
    public function disableTwoFactor(): void;

    /**
     * Get the user's two-factor recovery codes relationship.
     */
    public function twoFactorRecoveryCodes(): HasMany;
}
