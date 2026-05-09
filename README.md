# Filament Multi-Factor Auth (MFA)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/asubodh/filament-two-factor-auth.svg?style=flat-square)](https://packagist.org/packages/asubodh/filament-two-factor-auth)
[![License](https://img.shields.io/packagist/l/asubodh/filament-two-factor-auth.svg?style=flat-square)](https://packagist.org/packages/asubodh/filament-two-factor-auth)

A robust, production-ready **Multi-Factor Authentication (MFA)** plugin designed specifically for [Filament](https://filamentphp.com) v5 admin panels. 

Created by **Subodh Aryal (@asubodh)**, this package provides a seamless and highly secure integration of TOTP-based authentication compatible with **Google Authenticator**, **Authy**, **Microsoft Authenticator**, and any standard TOTP application.

---

## ✨ Features

- 🔐 **TOTP Authentication** — Time-based One-Time Password (RFC 6238).
- 📱 **QR Code Setup** — Scan-to-configure with any authenticator app.
- 🔑 **Recovery Codes** — One-time backup codes with hashed database storage.
- 🛡️ **Login Challenge** — Native OTP verification page displayed securely after standard login.
- 🚨 **Enforce 2FA** — Option to force all users to set up 2FA before they can access the panel.
- 🍪 **Trusted Devices** — Optional "remember this device" functionality.
- ⚡ **Rate Limiting** — Built-in brute-force protection on OTP attempts.
- 🔒 **Encrypted Secrets** — TOTP secrets are encrypted at rest using Laravel Crypt.
- 📡 **Event System** — Listen to events for enable, disable, verify, and failed attempts.
- 🎨 **Native Filament UI** — Clean, dark-mode compatible settings and challenge pages that blend perfectly with your panel.

---

## 📋 Requirements

- PHP 8.2+
- Laravel 11.0+
- Filament v5.0+

---

## 🚀 Installation & Setup

### 1. Install the Package

Pull the package into your project using Composer:

```bash
composer require asubodh/filament-two-factor-auth
```

### 2. Publish and Run Migrations

Publish the necessary database migrations and run them. This will add the required columns to your `users` table and create a new `two_factor_recovery_codes` table.

```bash
php artisan vendor:publish --tag="two-factor-auth-migrations"
php artisan migrate
```

### 3. Publish Configuration (Optional)

You can publish the configuration file to customize the default behavior:

```bash
php artisan vendor:publish --tag="two-factor-auth-config"
```

### 4. Prepare Your User Model

Update your `User` model to implement the `TwoFactorAuthenticatable` interface and use the `HasTwoFactorAuth` trait.

```php
<?php

namespace App\Models;

use Asubodh\FilamentTwoFactorAuth\Contracts\TwoFactorAuthenticatable;
use Asubodh\FilamentTwoFactorAuth\Traits\HasTwoFactorAuth;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements TwoFactorAuthenticatable
{
    use HasTwoFactorAuth;

    // ... your existing code
}
```

### 5. Register the Plugin in Your Panel

Add the `TwoFactorPlugin` to your Filament panel configuration (e.g., `app/Providers/Filament/AdminPanelProvider.php`).

```php
<?php

namespace App\Providers\Filament;

use Asubodh\FilamentTwoFactorAuth\TwoFactorPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // ...
            ->plugin(
                TwoFactorPlugin::make()
                    ->showInNavigation(true)
                    ->showInProfileMenu(true)
            );
    }
}
```

That's it! Your admin panel now has full 2FA support.

---

## ⚙️ Configuration & Options

### Plugin Fluent API

The plugin provides a fluent API for easy configuration directly within your Panel Provider:

```php
->plugin(
    TwoFactorPlugin::make()
        // The name displayed in the user's authenticator app
        ->issuer('My Amazing SaaS') 
        
        // Force all users to set up 2FA before accessing the panel.
        // If they haven't set it up, they will be redirected to the setup page.
        ->enforceForAllUsers(true) 
        
        // Enable "remember this device" functionality (default: false)
        ->rememberDevice(enabled: true, days: 30) 
        
        // Navigation options
        ->showInNavigation(true) 
        ->showInProfileMenu(true)
        ->navigationIcon('heroicon-o-shield-check')
        ->navigationGroup('Security') 
        ->navigationSort(100)
)
```

### Configuration File (`config/two-factor-auth.php`)

If you published the config file, you can modify deeper system settings:

| Option | Default | Description |
|--------|---------|-------------|
| `issuer` | `config('app.name')` | Name shown in authenticator apps. |
| `window` | `1` | TOTP verification window (±30s per unit). Handles slight clock drift. |
| `encrypt_secret` | `true` | Encrypt TOTP secrets at rest. |
| `recovery_codes.count` | `8` | Number of recovery codes generated for the user. |
| `recovery_codes.length` | `10` | Character length of each recovery code. |
| `rate_limit.max_attempts` | `5` | Maximum OTP attempts allowed before temporary lockout. |
| `rate_limit.decay_minutes` | `1` | Lockout duration in minutes after exceeding max attempts. |
| `remember_device.enabled` | `false` | Enable trusted device cookies. |
| `remember_device.days` | `30` | Lifetime of the trusted device cookie in days. |

---

## 🛠️ How It Works

### Enabling 2FA
1. The user navigates to **Two-Factor Auth** via the sidebar or profile menu.
2. They click **Enable Two-Factor Authentication**.
3. A QR code is generated. The user scans it with their authenticator app.
4. The user verifies the setup by entering the current 6-digit code.
5. The system generates and displays one-time recovery codes for the user to securely store.

### Login Flow (The Challenge)
1. The user logs in with their standard email and password.
2. The `EnsureTwoFactorAuthenticated` middleware detects that 2FA is active.
3. The user is securely redirected to the **Two-Factor Challenge** page.
4. The user enters their 6-digit TOTP code (or opts to use a recovery code).
5. Upon success, the session is marked as verified, and the user gains full access.

### Enforcing 2FA (`enforceForAllUsers`)
If `enforceForAllUsers()` is enabled on the plugin, any user who successfully logs in but hasn't configured 2FA will be immediately locked to the **Two-Factor Auth** settings page. A prominent warning will instruct them that setup is required by the administrator. They cannot navigate to any other page until setup is complete.

### Trusted Devices (Remember Device)
When `rememberDevice()` is enabled, a secure, HMAC-signed, HTTP-only cookie is created on the user's browser after they successfully pass the 2FA challenge. For the duration of this cookie (default: 30 days), the user will not be prompted for a 2FA code again on that specific device, even if their session expires and they log back in with their password. This dramatically improves user experience without heavily compromising security.

---

## 📡 Events

The package fires various events that you can listen to in your application (e.g., for logging audit trails or sending notifications).

| Event | Payload | Description |
|-------|---------|-------------|
| `TwoFactorEnabled` | `$user` | Fired when a user successfully enables 2FA. |
| `TwoFactorDisabled` | `$user` | Fired when a user disables 2FA. |
| `TwoFactorVerified` | `$user` | Fired when a user successfully passes the 2FA login challenge. |
| `TwoFactorFailed` | `$user`, `$reason` | Fired when a user enters an invalid TOTP or recovery code. |

### Example Event Subscriber

Instead of creating separate listeners for each event, you can use an **Event Subscriber** to handle all 2FA events cleanly in one file.

Because Laravel 11+ has **automatic event discovery** enabled by default, you just need to create this class in `app/Listeners/TwoFactorEventSubscriber.php`. Laravel will automatically detect the methods starting with `handle` and register them!

```php
<?php

namespace App\Listeners;

use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorDisabled;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorEnabled;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorFailed;
use Asubodh\FilamentTwoFactorAuth\Events\TwoFactorVerified;
use Illuminate\Support\Facades\Log;

class TwoFactorEventSubscriber
{
    public function handleTwoFactorEnabled(TwoFactorEnabled $event): void
    {
        Log::info("User ID {$event->user->id} enabled 2FA.");
    }

    public function handleTwoFactorDisabled(TwoFactorDisabled $event): void
    {
        Log::warning("User ID {$event->user->id} disabled 2FA.");
    }

    public function handleTwoFactorVerified(TwoFactorVerified $event): void
    {
        Log::info("User ID {$event->user->id} successfully passed the 2FA challenge.");
    }

    public function handleTwoFactorFailed(TwoFactorFailed $event): void
    {
        Log::alert("User ID {$event->user->id} failed 2FA challenge. Reason: {$event->reason}");
    }
}
```

---

## 🛡️ Security Measures

Security is the primary focus of this package:
- **Secret Storage:** TOTP secrets are encrypted at rest using Laravel's `Crypt` facade (AES-256-CBC/AES-128-CBC).
- **Recovery Codes:** Stored as `bcrypt` hashes, ensuring that even in the event of a database breach, plain-text backup codes are not exposed.
- **Brute-Force Protection:** Built-in rate limiting (defaults to 5 attempts per minute) prevents attackers from guessing OTPs.
- **Clock Drift Mitigation:** The verification window allows for ±1 period (30 seconds) to account for minor time sync issues on the user's device.
- **Session Fixation:** The user's session is regenerated immediately after passing the 2FA challenge.

---

## 💾 Database Changes

When you run the migrations, the following happens:

1. **`users` Table Additions:**
   - `two_factor_secret` (text, nullable) — The encrypted TOTP secret.
   - `two_factor_enabled` (boolean) — Status flag.
   - `two_factor_confirmed_at` (timestamp, nullable) — When setup was finalized.

2. **`two_factor_recovery_codes` Table:**
   - Tracks the hashed recovery codes, which user they belong to, and timestamps for creation and usage.

---

## 🧪 Testing

```bash
composer test
```

---

## 🤝 Contributing

Contributions are always welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

---

## 👤 Credits

- **[Subodh Aryal (asubodh)](https://github.com/asubodh)** - Creator & Lead Developer
- [All Contributors](../../contributors)

---

## 📄 License

The MIT License (MIT). Please see the [License File](LICENSE) for more information.
