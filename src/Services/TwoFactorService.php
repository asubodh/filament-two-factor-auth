<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth\Services;

use Asubodh\FilamentTwoFactorAuth\Contracts\TwoFactorAuthenticatable;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorDisabled;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorEnabled;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorFailed;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorVerified;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    /**
     * Generate a new TOTP secret key.
     */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /**
     * Generate an SVG QR code for the given user and secret.
     *
     * The QR code encodes a TOTP URI compatible with Google Authenticator,
     * Authy, Microsoft Authenticator, and other TOTP apps.
     */
    public function getQrCodeSvg(TwoFactorAuthenticatable $user, string $secret, int $size = 200): string
    {
        $issuer = config('two-factor-auth.issuer', config('app.name', 'Laravel'));

        // Build the otpauth:// URI
        $qrCodeUrl = $this->engine->getQRCodeUrl(
            $issuer,
            $this->getUserIdentifier($user),
            $secret,
        );

        // Render as SVG using BaconQrCode
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd(),
        );

        $writer = new Writer($renderer);

        return $writer->writeString($qrCodeUrl);
    }

    /**
     * Generate a base64-encoded data URI for the QR code SVG.
     * Useful for embedding directly in HTML img tags.
     */
    public function getQrCodeDataUri(TwoFactorAuthenticatable $user, string $secret, int $size = 200): string
    {
        $svg = $this->getQrCodeSvg($user, $secret, $size);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Verify a TOTP code against a user's secret.
     */
    public function verifyCode(TwoFactorAuthenticatable $user, string $code): bool
    {
        $secret = $user->getTwoFactorSecret();

        if ($secret === null) {
            return false;
        }

        $window = (int) config('two-factor-auth.window', 1);

        $valid = $this->engine->verifyKey($secret, $code, $window);

        if ($valid) {
            TwoFactorVerified::dispatch($user);
        } else {
            TwoFactorFailed::dispatch($user, 'invalid_code');
        }

        return $valid;
    }

    /**
     * Verify a TOTP code against a raw (unencrypted) secret.
     * Used during the initial setup confirmation before the secret is persisted.
     */
    public function verifyCodeAgainstSecret(string $secret, string $code): bool
    {
        $window = (int) config('two-factor-auth.window', 1);

        return $this->engine->verifyKey($secret, $code, $window);
    }

    /**
     * Enable two-factor authentication for a user.
     */
    public function enableForUser(TwoFactorAuthenticatable $user, string $secret): void
    {
        $user->enableTwoFactor($secret);

        TwoFactorEnabled::dispatch($user);
    }

    /**
     * Disable two-factor authentication for a user.
     */
    public function disableForUser(TwoFactorAuthenticatable $user): void
    {
        $user->disableTwoFactor();

        TwoFactorDisabled::dispatch($user);
    }

    /**
     * Check if a user has two-factor authentication enabled.
     */
    public function isEnabled(TwoFactorAuthenticatable $user): bool
    {
        return $user->hasTwoFactorEnabled();
    }

    /**
     * Get the Google2FA engine instance.
     */
    public function getEngine(): Google2FA
    {
        return $this->engine;
    }

    /**
     * Get a display identifier for the user (typically email).
     */
    protected function getUserIdentifier(TwoFactorAuthenticatable $user): string
    {
        if (method_exists($user, 'getEmailForVerification')) {
            return $user->getEmailForVerification();
        }

        if (property_exists($user, 'email') || isset($user->email)) {
            return $user->email;
        }

        return (string) $user->getAuthIdentifier();
    }
}
