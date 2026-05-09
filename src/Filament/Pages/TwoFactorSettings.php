<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth\Filament\Pages;

use Asubodh\FilamentTwoFactorAuth\Contracts\TwoFactorAuthenticatable;
use Asubodh\FilamentTwoFactorAuth\Services\RecoveryCodeService;
use Asubodh\FilamentTwoFactorAuth\Services\TwoFactorService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
/**
 * Filament page for managing two-factor authentication settings.
 * Allows users to enable, disable, and manage their 2FA configuration.
 */
class TwoFactorSettings extends Page
{
    protected string $view = 'two-factor-auth::filament.pages.two-factor-settings';

    public static function shouldRegisterNavigation(): bool
    {
        return \Asubodh\FilamentTwoFactorAuth\TwoFactorPlugin::get()->shouldShowInNavigation();
    }

    public static function getNavigationGroup(): ?string
    {
        return \Asubodh\FilamentTwoFactorAuth\TwoFactorPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return \Asubodh\FilamentTwoFactorAuth\TwoFactorPlugin::get()->getNavigationSort();
    }

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return \Asubodh\FilamentTwoFactorAuth\TwoFactorPlugin::get()->getNavigationIcon() ?? 'heroicon-o-shield-check';
    }

    public static function getNavigationLabel(): string
    {
        return \Asubodh\FilamentTwoFactorAuth\TwoFactorPlugin::get()->getNavigationLabel() ?? __('Two-Factor Auth');
    }

    // Setup state
    public bool $isSettingUp = false;

    public ?string $setupSecret = null;

    public ?string $setupQrCode = null;

    public ?string $confirmationCode = '';

    // Disable state
    public ?string $disableCode = '';

    // Recovery codes display
    public ?array $recoveryCodes = null;

    public bool $showRecoveryCodes = false;

    public function getTitle(): string|Htmlable
    {
        return __('Two-Factor Authentication');
    }


    public static function getSlug(?Panel $panel = null): string
    {
        return config('two-factor-auth.settings_route', 'two-factor-settings');
    }

    public function mount(): void
    {
        $this->isSettingUp = false;
        $this->setupSecret = null;
        $this->setupQrCode = null;
        $this->recoveryCodes = null;
        $this->showRecoveryCodes = false;
    }

    /**
     * Get the authenticated user, verified as TwoFactorAuthenticatable.
     */
    protected function getUser(): TwoFactorAuthenticatable
    {
        $user = Filament::auth()->user();

        if (!$user instanceof TwoFactorAuthenticatable) {
            throw new \RuntimeException('User model must implement TwoFactorAuthenticatable interface.');
        }

        return $user;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->getUser()->hasTwoFactorEnabled();
    }

    public function regenerateRecoveryCodesAction(): Action
    {
        return Action::make('regenerateRecoveryCodes')
            ->label(__('Regenerate Recovery Codes'))
            ->color('gray')
            ->icon('heroicon-m-arrow-path')
            ->requiresConfirmation()
            ->modalHeading(__('Regenerate Recovery Codes'))
            ->modalDescription(__('Are you sure you want to regenerate your recovery codes? Your existing codes will be permanently invalidated.'))
            ->modalSubmitActionLabel(__('Regenerate'))
            ->action(fn () => $this->regenerateRecoveryCodes());
    }

    public function disableTwoFactorAction(): Action
    {
        return Action::make('disableTwoFactor')
            ->label(__('Disable Two-Factor Authentication'))
            ->color('danger')
            ->icon('heroicon-m-shield-exclamation')
            ->action(fn () => $this->disableTwoFactor());
    }

    // -------------------------------------------------------
    // Enable 2FA Flow
    // -------------------------------------------------------

    /**
     * Start the 2FA setup process — generates a secret and QR code.
     */
    public function startSetup(): void
    {
        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        $this->setupSecret = $service->generateSecret();
        $this->setupQrCode = $service->getQrCodeDataUri($this->getUser(), $this->setupSecret);
        $this->isSettingUp = true;
        $this->confirmationCode = '';
    }

    /**
     * Cancel the setup process without enabling 2FA.
     */
    public function cancelSetup(): void
    {
        $this->isSettingUp = false;
        $this->setupSecret = null;
        $this->setupQrCode = null;
        $this->confirmationCode = '';
    }

    /**
     * Confirm the setup by verifying the user's TOTP code.
     * Only after verification, 2FA is actually enabled.
     */
    public function confirmSetup(): void
    {
        $this->validate([
            'confirmationCode' => ['required', 'string', 'size:6'],
        ]);

        if (!$this->setupSecret) {
            Notification::make()
                ->title(__('Setup Error'))
                ->body(__('No setup in progress. Please start over.'))
                ->danger()
                ->send();

            $this->cancelSetup();

            return;
        }

        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        // Verify the code against the raw secret (not yet stored on user)
        if (!$service->verifyCodeAgainstSecret($this->setupSecret, $this->confirmationCode)) {
            Notification::make()
                ->title(__('Invalid Code'))
                ->body(__('The authentication code you entered is invalid. Please scan the QR code and try again.'))
                ->danger()
                ->send();

            $this->confirmationCode = '';

            return;
        }

        // Code verified — enable 2FA
        $service->enableForUser($this->getUser(), $this->setupSecret);

        // Generate recovery codes
        /** @var RecoveryCodeService $recoveryService */
        $recoveryService = app(RecoveryCodeService::class);
        $this->recoveryCodes = $recoveryService->generate($this->getUser());
        $this->showRecoveryCodes = true;

        // Clean up setup state
        $this->isSettingUp = false;
        $this->setupSecret = null;
        $this->setupQrCode = null;
        $this->confirmationCode = '';

        Notification::make()
            ->title(__('Two-Factor Authentication Enabled'))
            ->body(__('Your account is now protected with two-factor authentication. Save your recovery codes in a safe place.'))
            ->success()
            ->send();
    }

    // -------------------------------------------------------
    // Disable 2FA Flow
    // -------------------------------------------------------

    /**
     * Disable two-factor authentication after verifying OTP.
     */
    public function disableTwoFactor(): void
    {
        $code = (string) $this->disableCode;

        if (empty($code) || strlen($code) !== 6 || !is_numeric($code)) {
            Notification::make()
                ->title(__('Validation Failed'))
                ->body(__('Please enter a valid 6-digit authenticator code.'))
                ->warning()
                ->send();

            return;
        }

        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        // Must verify OTP before disabling
        if (!$service->verifyCode($this->getUser(), $code)) {
            Notification::make()
                ->title(__('Verification Failed'))
                ->body(__('The authentication code you entered is invalid. 2FA was not disabled.'))
                ->danger()
                ->send();

            $this->disableCode = '';

            return;
        }

        $service->disableForUser($this->getUser());

        // Clear state
        $this->disableCode = '';
        $this->recoveryCodes = null;
        $this->showRecoveryCodes = false;

        // Clear the 2FA verified session flag
        session()->forget('two_factor_verified');

        Notification::make()
            ->title(__('Two-Factor Authentication Disabled'))
            ->body(__('Two-factor authentication has been disabled for your account.'))
            ->warning()
            ->send();
    }

    // -------------------------------------------------------
    // Recovery Codes Management
    // -------------------------------------------------------

    /**
     * Regenerate recovery codes — invalidates all existing codes.
     */
    public function regenerateRecoveryCodes(): void
    {
        /** @var RecoveryCodeService $service */
        $service = app(RecoveryCodeService::class);

        $this->recoveryCodes = $service->regenerate($this->getUser());
        $this->showRecoveryCodes = true;

        Notification::make()
            ->title(__('Recovery Codes Regenerated'))
            ->body(__('Your old recovery codes have been invalidated. Save these new codes in a safe place.'))
            ->success()
            ->send();
    }

    /**
     * Download recovery codes as a text file.
     */
    public function downloadRecoveryCodes()
    {
        $text = $this->getRecoveryCodesAsText();
        
        return response()->streamDownload(function () use ($text) {
            echo $text;
        }, 'two-factor-recovery-codes.txt');
    }

    /**
     * Dispatch an event to copy codes to clipboard and show notification.
     */
    public function copyRecoveryCodes(): void
    {
        $this->dispatch('copy-to-clipboard', text: $this->getRecoveryCodesAsText());
        
        Notification::make()
            ->title(__('Codes copied to clipboard'))
            ->success()
            ->send();
    }

    /**
     * Dismiss the recovery codes display.
     */
    public function dismissRecoveryCodes(): void
    {
        $this->recoveryCodes = null;
        $this->showRecoveryCodes = false;
    }

    /**
     * Get the remaining count of unused recovery codes.
     */
    public function getRemainingRecoveryCodesCount(): int
    {
        return $this->getUser()->remainingRecoveryCodes();
    }

    /**
     * Format recovery codes as a downloadable text string.
     */
    public function getRecoveryCodesAsText(): string
    {
        if (!$this->recoveryCodes) {
            return '';
        }

        $appName = config('app.name', 'Laravel');
        $lines = [
            "{$appName} — Two-Factor Recovery Codes",
            'Generated: ' . now()->format('Y-m-d H:i:s T'),
            str_repeat('-', 45),
            '',
        ];

        foreach ($this->recoveryCodes as $index => $code) {
            $lines[] = ($index + 1) . '. ' . $code;
        }

        $lines[] = '';
        $lines[] = str_repeat('-', 45);
        $lines[] = 'Each code can only be used once.';
        $lines[] = 'Store these codes in a secure location.';

        return implode("\n", $lines);
    }
}
