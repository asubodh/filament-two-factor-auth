<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth;

use Asubodh\FilamentTwoFactorAuth\Filament\Pages\TwoFactorChallenge;
use Asubodh\FilamentTwoFactorAuth\Filament\Pages\TwoFactorSettings;
use Asubodh\FilamentTwoFactorAuth\Http\Middleware\EnsureTwoFactorAuthenticated;
use Filament\Contracts\Plugin;
use Filament\Panel;

class TwoFactorPlugin implements Plugin
{
    /**
     * Whether to enforce 2FA for all users in this panel.
     */
    protected bool $enforceForAllUsers = false;

    /**
     * Custom issuer name for the TOTP setup.
     */
    protected ?string $issuer = null;

    /**
     * Whether to enable the "remember device" feature.
     */
    protected bool $rememberDevice = false;

    /**
     * Number of days to remember a trusted device.
     */
    protected int $rememberDeviceDays = 30;

    /**
     * Whether to show the settings page in navigation.
     */
    protected bool $showInNavigation = true;

    /**
     * Custom navigation group for the settings page.
     */
    protected ?string $navigationGroup = 'Account';

    /**
     * Custom navigation sort order.
     */
    protected ?int $navigationSort = 100;

    /**
     * Get the unique identifier for this plugin.
     */
    public function getId(): string
    {
        return 'filament-two-factor-auth';
    }

    /**
     * Static factory method for fluent instantiation.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Get the plugin instance from the current panel.
     */
    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    // -------------------------------------------------------
    // Fluent Configuration API
    // -------------------------------------------------------

    /**
     * Enforce 2FA for all users in this panel.
     * When enabled, users without 2FA will be prompted to set it up.
     */
    public function enforceForAllUsers(bool $enforce = true): static
    {
        $this->enforceForAllUsers = $enforce;

        return $this;
    }

    /**
     * Set the issuer name displayed in authenticator apps.
     */
    public function issuer(string $issuer): static
    {
        $this->issuer = $issuer;

        return $this;
    }

    /**
     * Enable the "remember device" (trusted device) feature.
     */
    public function rememberDevice(bool $enabled = true, int $days = 30): static
    {
        $this->rememberDevice = $enabled;
        $this->rememberDeviceDays = $days;

        return $this;
    }

    /**
     * Whether to show the settings page in the user profile menu.
     */
    protected bool $showInProfileMenu = false;

    /**
     * Set whether the settings page appears in navigation.
     */
    public function showInNavigation(bool $show = true): static
    {
        $this->showInNavigation = $show;

        return $this;
    }

    /**
     * Set whether the settings page appears in the user profile menu.
     */
    public function showInProfileMenu(bool $show = true): static
    {
        $this->showInProfileMenu = $show;

        return $this;
    }

    protected ?string $navigationIcon = 'heroicon-o-shield-check';

    public function navigationIcon(?string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    protected ?string $navigationLabel = null;

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    protected ?string $userMenuItemIcon = 'heroicon-o-shield-check';

    public function userMenuItemIcon(?string $icon): static
    {
        $this->userMenuItemIcon = $icon;

        return $this;
    }

    protected ?string $userMenuItemLabel = null;

    public function userMenuItemLabel(?string $label): static
    {
        $this->userMenuItemLabel = $label;

        return $this;
    }

    protected int $window = 1;

    public function window(int $window): static
    {
        $this->window = $window;

        return $this;
    }

    protected int $rateLimitMaxAttempts = 5;
    protected int $rateLimitDecayMinutes = 1;

    public function rateLimit(int $maxAttempts = 5, int $decayMinutes = 1): static
    {
        $this->rateLimitMaxAttempts = $maxAttempts;
        $this->rateLimitDecayMinutes = $decayMinutes;

        return $this;
    }

    /**
     * Set the navigation group for the settings page.
     */
    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    /**
     * Set the navigation sort order for the settings page.
     */
    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    // -------------------------------------------------------
    // Getters
    // -------------------------------------------------------

    public function shouldEnforceForAllUsers(): bool
    {
        return $this->enforceForAllUsers;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function shouldRememberDevice(): bool
    {
        return $this->rememberDevice;
    }

    public function getRememberDeviceDays(): int
    {
        return $this->rememberDeviceDays;
    }

    public function shouldShowInNavigation(): bool
    {
        return $this->showInNavigation;
    }

    public function shouldShowInProfileMenu(): bool
    {
        return $this->showInProfileMenu;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function getUserMenuItemIcon(): ?string
    {
        return $this->userMenuItemIcon;
    }

    public function getUserMenuItemLabel(): ?string
    {
        return $this->userMenuItemLabel;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    // -------------------------------------------------------
    // Plugin Lifecycle
    // -------------------------------------------------------

    /**
     * Register the plugin with the panel.
     * Called during panel initialization.
     */
    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                TwoFactorSettings::class,
                TwoFactorChallenge::class,
            ])
            ->authMiddleware([
                EnsureTwoFactorAuthenticated::class,
            ]);
    }

    /**
     * Boot the plugin — called when the panel is actively in use.
     * Apply runtime configuration from the fluent API.
     */
    public function boot(Panel $panel): void
    {
        // Apply issuer override to config
        if ($this->issuer !== null) {
            config(['two-factor-auth.issuer' => $this->issuer]);
        }

        // Apply remember device settings to config
        if ($this->rememberDevice) {
            config([
                'two-factor-auth.remember_device.enabled' => true,
                'two-factor-auth.remember_device.days' => $this->rememberDeviceDays,
            ]);
        }

        // Apply window and rate limits
        config([
            'two-factor-auth.window' => $this->window,
            'two-factor-auth.rate_limit.max_attempts' => $this->rateLimitMaxAttempts,
            'two-factor-auth.rate_limit.decay_minutes' => $this->rateLimitDecayMinutes,
        ]);

        // Register User Menu Item if configured
        if ($this->shouldShowInProfileMenu()) {
            $panel->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label($this->getUserMenuItemLabel() ?? __('Two-Factor Auth'))
                    ->url(fn(): string => TwoFactorSettings::getUrl())
                    ->icon($this->getUserMenuItemIcon() ?? 'heroicon-o-shield-check'),
            ]);
        }
    }
}
