<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        {{-- Status Banner --}}
        @php
            $plugin = null;
            try {
                $plugin = \Asubodh\FilamentTwoFactorAuth\TwoFactorPlugin::get();
            } catch (\Exception $e) {}
            
            $isEnforced = $plugin ? $plugin->shouldEnforceForAllUsers() : false;
        @endphp

        @if ($this->isTwoFactorEnabled())
            <x-filament::section icon="heroicon-o-shield-check" icon-color="success">
                <x-slot name="heading">
                    {{ __('Two-factor authentication is enabled') }}
                </x-slot>
                <x-slot name="description">
                    {{ __('Your account is protected with two-factor authentication. You will be asked for a verification code each time you sign in.') }}
                </x-slot>
            </x-filament::section>
        @else
            @if ($isEnforced)
                <div style="color: rgb(153, 27, 27); background-color: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 0.5rem; display: flex; gap: 0.75rem; align-items: center; border: 1px solid rgba(239, 68, 68, 0.3); margin-bottom: 0.5rem;">
                    <x-filament::icon icon="heroicon-m-shield-exclamation" style="width: 1.5rem; height: 1.5rem;" />
                    <span style="font-size: 0.875rem; font-weight: 500;">{{ __('Two-factor authentication is required by your administrator. You must set it up to continue using the application.') }}</span>
                </div>
            @endif

            <x-filament::section icon="heroicon-o-shield-exclamation" icon-color="warning">
                <x-slot name="heading">
                    {{ __('Two-factor authentication is not enabled') }}
                </x-slot>
                <x-slot name="description">
                    {{ __('Add an extra layer of security to your account by enabling two-factor authentication. You will need an authenticator app like Google Authenticator or Authy.') }}
                </x-slot>
            </x-filament::section>
        @endif

        {{-- Enable 2FA Setup Flow --}}
        @if (! $this->isTwoFactorEnabled() && ! $isSettingUp)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Set Up Two-Factor Authentication') }}
                </x-slot>
                <x-slot name="description">
                    {{ __('Protect your account with an authenticator app. Compatible with Google Authenticator, Authy, Microsoft Authenticator, and more.') }}
                </x-slot>

                <div style="margin-top: 1rem;">
                    <x-filament::button wire:click="startSetup" icon="heroicon-m-shield-check" size="lg">
                        {{ __('Enable Two-Factor Authentication') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- Setup In Progress (QR Code & Confirmation) --}}
        @if ($isSettingUp)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Scan QR Code') }}
                </x-slot>
                <x-slot name="description">
                    {{ __('Scan this QR code with your authenticator app, then enter the 6-digit verification code below to confirm setup.') }}
                </x-slot>

                <div style="display: flex; flex-direction: column; align-items: center; gap: 1.5rem; margin-top: 1.5rem; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid rgba(156, 163, 175, 0.2);">
                    {{-- QR Code Display --}}
                    @if ($setupQrCode)
                        <div style="padding: 1rem; background-color: white; border-radius: 0.5rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
                            <img src="{{ $setupQrCode }}" alt="QR Code" style="width: 12rem; height: 12rem;" />
                        </div>
                    @endif

                    {{-- Manual Secret Key --}}
                    <div style="text-align: center;">
                        <p style="margin-bottom: 0.5rem; font-size: 0.875rem; color: rgba(156, 163, 175, 1);">
                            {{ __('Or enter this code manually') }}
                        </p>
                        <x-filament::badge color="gray" size="lg">
                            <span style="font-family: monospace; letter-spacing: 0.1em; padding: 0.25rem;">{{ $setupSecret }}</span>
                        </x-filament::badge>
                    </div>

                    {{-- Confirmation Code Input --}}
                    <div style="width: 100%; max-width: 24rem; margin-top: 1rem;">
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                wire:model="confirmationCode"
                                wire:keydown.enter="confirmSetup"
                                placeholder="000000"
                                maxlength="6"
                                style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5em; font-family: monospace; padding: 0.75rem;"
                            />
                        </x-filament::input.wrapper>

                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <div style="flex: 1;">
                                <x-filament::button wire:click="confirmSetup" icon="heroicon-m-check-circle" style="width: 100%; justify-content: center;">
                                    {{ __('Confirm & Enable') }}
                                </x-filament::button>
                            </div>
                            <div style="flex: 1;">
                                <x-filament::button wire:click="cancelSetup" color="gray" style="width: 100%; justify-content: center;">
                                    {{ __('Cancel') }}
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Recovery Codes Display (Shown once after enable/regenerate) --}}
        @if ($showRecoveryCodes && $recoveryCodes)
            <x-filament::section icon="heroicon-o-key" icon-color="warning">
                <x-slot name="heading">
                    {{ __('Recovery Codes') }}
                </x-slot>
                <x-slot name="description">
                    {{ __('Store these recovery codes in a secure location. Each code can only be used once to access your account if you lose your authenticator device.') }}
                </x-slot>

                <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 1rem;">
                    {{-- Warning Banner --}}
                    <div style="color: rgb(161, 98, 7); background-color: rgba(234, 179, 8, 0.1); padding: 1rem; border-radius: 0.5rem; display: flex; gap: 0.75rem; align-items: center; border: 1px solid rgba(234, 179, 8, 0.3);">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" style="width: 1.5rem; height: 1.5rem;" />
                        <span style="font-size: 0.875rem;">{{ __('These codes will only be shown once. If you lose them and your authenticator device, you will lose access to your account.') }}</span>
                    </div>

                    {{-- Codes Grid --}}
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem; padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(156, 163, 175, 0.2);">
                        @foreach ($recoveryCodes as $code)
                            <div style="padding: 0.5rem; text-align: center; font-family: monospace; border-radius: 0.25rem; border: 1px solid rgba(156, 163, 175, 0.3);">
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>

                    {{-- Action Buttons --}}
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem;" x-data x-on:copy-to-clipboard.window="navigator.clipboard.writeText($event.detail.text)">
                        <x-filament::button
                            color="gray"
                            icon="heroicon-m-clipboard-document"
                            wire:click="copyRecoveryCodes"
                        >
                            {{ __('Copy Codes') }}
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            icon="heroicon-m-arrow-down-tray"
                            wire:click="downloadRecoveryCodes"
                        >
                            {{ __('Download Codes') }}
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            icon="heroicon-m-x-mark"
                            wire:click="dismissRecoveryCodes"
                        >
                            {{ __('I\'ve Saved These Codes') }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Recovery Codes & Disable 2FA Settings --}}
        @if ($this->isTwoFactorEnabled())
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                
                {{-- RECOVERY CODES MANAGEMENT (when 2FA is enabled) --}}
                @if (! $showRecoveryCodes)
                    <x-filament::section>
                        <x-slot name="heading">
                            {{ __('Recovery Codes') }}
                        </x-slot>

                        <x-slot name="description">
                            {{ __('Recovery codes can be used to access your account if you lose your authenticator device.') }}
                        </x-slot>

                        <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 1rem; align-items: flex-start;">
                            @php
                                $count = $this->getRemainingRecoveryCodesCount();
                                $color = $count > 2 ? 'success' : ($count > 0 ? 'warning' : 'danger');
                            @endphp
                            
                            <x-filament::badge :color="$color" icon="heroicon-m-key">
                                {{ trans_choice(':count recovery code remaining|:count recovery codes remaining', $count, ['count' => $count]) }}
                            </x-filament::badge>

                            @if ($count <= 2)
                                <div style="color: rgb(161, 98, 7); background-color: rgba(234, 179, 8, 0.1); padding: 1rem; border-radius: 0.5rem; display: flex; gap: 0.75rem; align-items: center; width: 100%; border: 1px solid rgba(234, 179, 8, 0.3);">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" style="width: 1.5rem; height: 1.5rem;" />
                                    <span style="font-size: 0.875rem;">{{ __('You are running low on recovery codes. Please regenerate them to ensure you can always access your account.') }}</span>
                                </div>
                            @endif

                            <div style="margin-top: 0.5rem;">
                                {{ $this->getAction('regenerateRecoveryCodes') }}
                            </div>
                        </div>
                    </x-filament::section>
                @endif

                {{-- DISABLE 2FA --}}
                <x-filament::section>
                    <x-slot name="heading">
                        {{ __('Disable Two-Factor Authentication') }}
                    </x-slot>

                    <x-slot name="description">
                        {{ __('If you disable two-factor authentication, your account will only be protected by your password. Enter your current authenticator code to confirm.') }}
                    </x-slot>

                    <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 1rem;">
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                wire:model="disableCode"
                                wire:keydown.enter="disableTwoFactor"
                                placeholder="000000"
                                maxlength="6"
                                style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5em; font-family: monospace; padding: 0.75rem;"
                            />
                        </x-filament::input.wrapper>

                        <div>
                            {{ $this->getAction('disableTwoFactor') }}
                        </div>
                    </div>
                </x-filament::section>
                
            </div>
        @endif

    </div>
</x-filament-panels::page>
