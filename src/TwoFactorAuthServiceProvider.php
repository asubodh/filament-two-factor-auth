<?php

declare(strict_types=1);

namespace Asubodh\FilamentTwoFactorAuth;

use Asubodh\FilamentTwoFactorAuth\Services\RecoveryCodeService;
use Asubodh\FilamentTwoFactorAuth\Services\TwoFactorService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TwoFactorAuthServiceProvider extends PackageServiceProvider
{
    public static string $name = 'two-factor-auth';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasMigrations([
                'add_two_factor_columns_to_users_table',
                'create_two_factor_recovery_codes_table',
            ])
            ->hasViews();
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        \Filament\Support\Facades\FilamentAsset::register([
            \Filament\Support\Assets\Css::make('filament-two-factor-auth', __DIR__ . '/../resources/css/filament-two-factor-auth.css'),
        ], 'asubodh/filament-two-factor-auth');
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        // Register core services as singletons
        $this->app->singleton(TwoFactorService::class, function () {
            return new TwoFactorService();
        });

        $this->app->singleton(RecoveryCodeService::class, function () {
            return new RecoveryCodeService();
        });
    }

}
