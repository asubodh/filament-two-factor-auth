<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth\Http\Middleware;

use Asubodh\FilamentTwoFactorAuth\Contracts\TwoFactorAuthenticatable;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * If the authenticated user has 2FA enabled but hasn't verified in
     * the current session, redirect them to the challenge page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        // Not authenticated — let Filament handle it
        if (! $user) {
            return $next($request);
        }

        // User doesn't implement 2FA interface — skip
        if (! $user instanceof TwoFactorAuthenticatable) {
            return $next($request);
        }

        // 2FA not enabled for this user
        if (! $user->hasTwoFactorEnabled()) {
            $panel = Filament::getCurrentPanel();
            $plugin = $panel ? $panel->getPlugin('filament-two-factor-auth') : null;

            if ($plugin instanceof \Asubodh\FilamentTwoFactorAuth\TwoFactorPlugin && $plugin->shouldEnforceForAllUsers()) {
                $settingsRoute = config('two-factor-auth.settings_route', 'two-factor-settings');
                $currentPath = $request->path();

                if (
                    str_contains($currentPath, $settingsRoute)
                    || str_contains($currentPath, 'logout')
                    || str_contains($currentPath, 'livewire')
                    || $request->header('X-Livewire')
                ) {
                    return $next($request);
                }

                $settingsUrl = $panel
                    ? $panel->getUrl() . '/' . $settingsRoute
                    : '/' . $settingsRoute;

                return redirect()->to($settingsUrl);
            }

            // Not enforced, allow through
            return $next($request);
        }

        // Already verified in this session — allow through
        if ($request->session()->get('two_factor_verified', false)) {
            return $next($request);
        }

        // Check trusted device cookie (if enabled)
        if ($this->hasTrustedDeviceCookie($request, $user)) {
            $request->session()->put('two_factor_verified', true);

            return $next($request);
        }

        // Exclude the challenge page and logout routes to prevent redirect loops
        $challengeRoute = config('two-factor-auth.challenge_route', 'two-factor-challenge');
        $currentPath = $request->path();

        if (
            str_contains($currentPath, $challengeRoute)
            || str_contains($currentPath, 'logout')
            || str_contains($currentPath, 'livewire')
            || $request->header('X-Livewire')
        ) {
            return $next($request);
        }

        // Redirect to the 2FA challenge page
        $panel = Filament::getCurrentPanel();
        $challengeUrl = $panel
            ? $panel->getUrl() . '/' . $challengeRoute
            : '/' . $challengeRoute;

        return redirect()->to($challengeUrl);
    }

    /**
     * Check if the request has a valid trusted device cookie.
     */
    protected function hasTrustedDeviceCookie(Request $request, TwoFactorAuthenticatable $user): bool
    {
        if (! config('two-factor-auth.remember_device.enabled', false)) {
            return false;
        }

        $cookieName = config('two-factor-auth.remember_device.cookie', 'two_factor_trusted_device');
        $cookieValue = $request->cookie($cookieName);

        if (! $cookieValue) {
            return false;
        }

        // The cookie contains an HMAC hash of the user ID + a timestamp
        // Verify it matches the current user
        $expectedHash = $this->generateTrustedDeviceHash($user);

        return hash_equals($expectedHash, $cookieValue);
    }

    /**
     * Generate a trusted device cookie for the user.
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public static function createTrustedDeviceCookie(TwoFactorAuthenticatable $user): \Symfony\Component\HttpFoundation\Cookie
    {
        $cookieName = config('two-factor-auth.remember_device.cookie', 'two_factor_trusted_device');
        $days = (int) config('two-factor-auth.remember_device.days', 30);
        $hash = (new static())->generateTrustedDeviceHash($user);

        return Cookie::make(
            name: $cookieName,
            value: $hash,
            minutes: $days * 24 * 60,
            secure: true,
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    /**
     * Generate an HMAC hash for trusted device verification.
     */
    protected function generateTrustedDeviceHash(TwoFactorAuthenticatable $user): string
    {
        $identifier = $user->getAuthIdentifier();
        $secret = $user->getTwoFactorSecret() ?? '';
        $appKey = config('app.key');

        return hash_hmac('sha256', $identifier . '|' . $secret, $appKey);
    }
}
