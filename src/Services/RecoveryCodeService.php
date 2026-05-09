<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth\Services;

use Asubodh\FilamentTwoFactorAuth\Contracts\TwoFactorAuthenticatable;
use Asubodh\FilamentTwoFactorAuth\Models\TwoFactorRecoveryCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoveryCodeService
{
    /**
     * Generate a fresh set of recovery codes for the user.
     *
     * @return array<string> The plaintext recovery codes (shown to user once)
     */
    public function generate(TwoFactorAuthenticatable $user): array
    {
        $count = (int) config('two-factor-auth.recovery_codes.count', 8);
        $length = (int) config('two-factor-auth.recovery_codes.length', 10);

        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateCode($length);
        }

        // Store hashed versions in the database
        foreach ($codes as $code) {
            $user->twoFactorRecoveryCodes()->create([
                'code_hash' => Hash::make($code),
                'created_at' => now(),
            ]);
        }

        return $codes;
    }

    /**
     * Verify a recovery code for a user.
     *
     * On success, the matching code is immediately marked as used.
     *
     * @return bool Whether the code was valid and successfully consumed
     */
    public function verify(TwoFactorAuthenticatable $user, string $code): bool
    {
        $unusedCodes = $user->twoFactorRecoveryCodes()
            ->whereNull('used_at')
            ->get();

        foreach ($unusedCodes as $storedCode) {
            if (Hash::check($code, $storedCode->code_hash)) {
                $storedCode->markAsUsed();

                return true;
            }
        }

        return false;
    }

    /**
     * Regenerate recovery codes — deletes all existing codes and
     * creates a fresh set. Returns the plaintext codes.
     *
     * @return array<string> The new plaintext recovery codes
     */
    public function regenerate(TwoFactorAuthenticatable $user): array
    {
        // Delete all existing codes (used and unused)
        $user->twoFactorRecoveryCodes()->delete();

        // Generate fresh codes
        return $this->generate($user);
    }

    /**
     * Get the number of remaining (unused) recovery codes for a user.
     */
    public function remaining(TwoFactorAuthenticatable $user): int
    {
        return $user->twoFactorRecoveryCodes()
            ->whereNull('used_at')
            ->count();
    }

    /**
     * Generate a single recovery code string.
     *
     * Format: XXXXX-XXXXX (two groups separated by hyphen for readability)
     */
    protected function generateCode(int $length): string
    {
        $halfLength = (int) ceil($length / 2);

        $part1 = strtoupper(Str::random($halfLength));
        $part2 = strtoupper(Str::random($length - $halfLength));

        return $part1 . '-' . $part2;
    }
}
