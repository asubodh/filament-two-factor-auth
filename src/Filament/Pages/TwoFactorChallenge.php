<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth\Filament\Pages;

use Asubodh\FilamentTwoFactorAuth\Contracts\TwoFactorAuthenticatable;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorFailed;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorVerified;
use Asubodh\FilamentTwoFactorAuth\Http\Middleware\EnsureTwoFactorAuthenticated;
use Asubodh\FilamentTwoFactorAuth\Services\RecoveryCodeService;
use Asubodh\FilamentTwoFactorAuth\Services\TwoFactorService;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The 2FA challenge page shown after login when the user has 2FA enabled.
 * Supports both TOTP code and recovery code verification.
 */
class TwoFactorChallenge extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'two-factor-auth::filament.pages.two-factor-challenge';

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $code = '';

    public ?string $recovery_code = '';

    public bool $useRecoveryCode = false;

    public function getTitle(): string|Htmlable
    {
        return __('Two-Factor Authentication');
    }

    public function getHeading(): string|Htmlable
    {
        return __('Two-Factor Authentication');
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->useRecoveryCode) {
            return __('Enter one of your recovery codes to verify your identity.');
        }

        return __('Enter the 6-digit code from your authenticator app to continue.');
    }

    public function hasLogo(): bool
    {
        return true;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return config('two-factor-auth.challenge_route', 'two-factor-challenge');
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('code')
                ->label(__('Authentication Code'))
                ->placeholder('000000')
                ->autofocus()
                ->autocomplete('one-time-code')
                ->maxLength(6)
                ->minLength(6)
                ->required()
                ->visible(fn(): bool => !$this->useRecoveryCode)
                ->extraInputAttributes([
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'style' => 'text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem;',
                ]),

            TextInput::make('recovery_code')
                ->label(__('Recovery Code'))
                ->placeholder('XXXXX-XXXXX')
                ->autofocus()
                ->autocomplete('off')
                ->required()
                ->visible(fn(): bool => $this->useRecoveryCode)
                ->extraInputAttributes([
                    'style' => 'text-align: center; font-size: 1.1rem; letter-spacing: 0.15rem;',
                ]),
        ]);
    }

    /**
     * Attempt to verify the submitted code.
     */
    public function verify(): void
    {
        if (! $this->applyRateLimit()) {
            return;
        }

        $user = Filament::auth()->user();

        if (!$user instanceof TwoFactorAuthenticatable) {
            Notification::make()
                ->title(__('Authentication Error'))
                ->body(__('Your account is not configured for two-factor authentication.'))
                ->danger()
                ->send();

            return;
        }

        if ($this->useRecoveryCode) {
            $this->verifyWithRecoveryCode($user);
        } else {
            $this->verifyWithTotp($user);
        }
    }

    /**
     * Verify using a TOTP code from the authenticator app.
     */
    protected function verifyWithTotp(TwoFactorAuthenticatable $user): void
    {
        $data = $this->form->getState();
        $code = (string) ($data['code'] ?? '');

        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        if ($service->verifyCode($user, $code)) {
            $this->markAsVerified($user);
        } else {
            $this->handleFailedAttempt($user);
        }
    }

    /**
     * Verify using a one-time recovery code.
     */
    protected function verifyWithRecoveryCode(TwoFactorAuthenticatable $user): void
    {
        $data = $this->form->getState();
        $code = $data['recovery_code'] ?? '';

        /** @var RecoveryCodeService $service */
        $service = app(RecoveryCodeService::class);

        if ($service->verify($user, $code)) {
            TwoFactorVerified::dispatch($user);
            $this->markAsVerified($user);

            // Warn user about remaining codes
            $remaining = $service->remaining($user);
            if ($remaining <= 2) {
                Notification::make()
                    ->title(__('Low Recovery Codes'))
                    ->body(__('You have :count recovery code(s) remaining. Please regenerate your codes.', ['count' => $remaining]))
                    ->warning()
                    ->persistent()
                    ->send();
            }
        } else {
            TwoFactorFailed::dispatch($user, 'invalid_recovery_code');
            $this->handleFailedAttempt($user);
        }
    }

    /**
     * Mark the session as 2FA verified and redirect.
     */
    protected function markAsVerified(TwoFactorAuthenticatable $user): void
    {
        // Regenerate session to prevent fixation
        session()->regenerate();
        session()->put('two_factor_verified', true);

        // Set trusted device cookie if enabled
        if (config('two-factor-auth.remember_device.enabled', false)) {
            $cookie = EnsureTwoFactorAuthenticated::createTrustedDeviceCookie($user);
            \Illuminate\Support\Facades\Cookie::queue($cookie);
        }

        // Redirect to intended URL or panel dashboard
        $this->redirect(session()->pull('url.intended', Filament::getUrl()));
    }

    /**
     * Handle a failed verification attempt.
     */
    protected function handleFailedAttempt(TwoFactorAuthenticatable $user): void
    {
        Notification::make()
            ->title(__('Verification Failed'))
            ->body($this->useRecoveryCode
                ? __('The recovery code you entered is invalid or has already been used.')
                : __('The authentication code you entered is invalid. Please try again.'))
            ->danger()
            ->send();

        // Clear the form inputs
        $this->code = '';
        $this->recovery_code = '';
    }

    /**
     * Toggle between TOTP code and recovery code input.
     */
    public function toggleRecoveryCode(): void
    {
        $this->useRecoveryCode = !$this->useRecoveryCode;
        $this->code = '';
        $this->recovery_code = '';
    }

    /**
     * Apply rate limiting to prevent brute-force attempts.
     */
    protected function applyRateLimit(): bool
    {
        $maxAttempts = (int) config('two-factor-auth.rate_limit.max_attempts', 5);
        $decayMinutes = (int) config('two-factor-auth.rate_limit.decay_minutes', 1);

        $key = 'two-factor-challenge:' . Filament::auth()->id();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            Notification::make()
                ->title(__('Too Many Attempts'))
                ->body(__('Please wait :seconds seconds before trying again.', ['seconds' => $seconds]))
                ->danger()
                ->send();

            return false;
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return true;
    }

    /**
     * Log out the user from the current session.
     */
    public function logout(): void
    {
        Filament::auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }
}
