<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth\Traits;

use Asubodh\FilamentTwoFactorAuth\Models\TwoFactorRecoveryCode;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * Trait to be used on User models to implement TwoFactorAuthenticatable.
 *
 * Add `use HasTwoFactorAuth;` to your User model and ensure it
 * implements the TwoFactorAuthenticatable interface.
 *
 * @property string|null $two_factor_secret
 * @property bool $two_factor_enabled
 * @property \Carbon\Carbon|null $two_factor_confirmed_at
 */
trait HasTwoFactorAuth
{
    /**
     * Initialize the trait — register casts for 2FA attributes.
     */
    public function initializeHasTwoFactorAuth(): void
    {
        $this->mergeCasts([
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ]);
    }

    /**
     * Get the decrypted two-factor secret key.
     */
    public function getTwoFactorSecret(): ?string
    {
        $secret = $this->attributes['two_factor_secret'] ?? null;

        if ($secret === null) {
            return null;
        }

        if (config('two-factor-auth.encrypt_secret', true)) {
            try {
                return Crypt::decryptString($secret);
            } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                return null;
            }
        }

        return $secret;
    }

    /**
     * Set the two-factor secret key (encrypts if configured).
     */
    public function setTwoFactorSecret(?string $secret): void
    {
        if ($secret !== null && config('two-factor-auth.encrypt_secret', true)) {
            $secret = Crypt::encryptString($secret);
        }

        $this->attributes['two_factor_secret'] = $secret;
    }

    /**
     * Determine if the user has two-factor authentication enabled and confirmed.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enableTwoFactor(string $secret): void
    {
        $this->setTwoFactorSecret($secret);
        $this->two_factor_enabled = true;
        $this->two_factor_confirmed_at = now();
        $this->save();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disableTwoFactor(): void
    {
        $this->setTwoFactorSecret(null);
        $this->two_factor_enabled = false;
        $this->two_factor_confirmed_at = null;
        $this->save();

        // Delete all recovery codes
        $this->twoFactorRecoveryCodes()->delete();
    }

    /**
     * Get the user's two-factor recovery codes.
     */
    public function twoFactorRecoveryCodes(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryCode::class, 'user_id');
    }

    /**
     * Get the count of remaining (unused) recovery codes.
     */
    public function remainingRecoveryCodes(): int
    {
        return $this->twoFactorRecoveryCodes()
            ->whereNull('used_at')
            ->count();
    }
}
