<x-filament-panels::page.simple>
    <form wire:submit="verify">
        {{ $this->form }}

        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 2rem;">
            <x-filament::button type="submit" wire:loading.attr="disabled" style="width: 100%; justify-content: center;" size="lg">
                <span wire:loading.remove wire:target="verify">{{ __('Verify') }}</span>
                <span wire:loading wire:target="verify">{{ __('Verifying...') }}</span>
            </x-filament::button>

            <x-filament::button color="gray" wire:click="toggleRecoveryCode" type="button" style="width: 100%; justify-content: center;" size="lg">
                @if ($useRecoveryCode)
                    <x-heroicon-m-device-phone-mobile style="width: 1.25rem; height: 1.25rem; margin-right: 0.5rem;" />
                    {{ __('Use Authenticator Code') }}
                @else
                    <x-heroicon-m-key style="width: 1.25rem; height: 1.25rem; margin-right: 0.5rem;" />
                    {{ __('Use Recovery Code') }}
                @endif
            </x-filament::button>
        </div>
    </form>

    <div style="text-align: center; margin-top: 1.5rem;">
        <button type="button" wire:click="logout" style="font-size: 0.875rem; font-weight: 500; text-decoration: underline;" class="text-gray-500 hover:text-danger-600 dark:text-gray-400 dark:hover:text-danger-400">
            {{ __('Sign out and use a different account') }}
        </button>
    </div>
</x-filament-panels::page.simple>
