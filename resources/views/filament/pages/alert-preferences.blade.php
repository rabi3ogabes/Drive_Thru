<x-filament-panels::page>
    {{-- This device: browser push permission and subscription (handled in the browser). --}}
    <x-filament::section :heading="__('alerts.preferences.device_heading')" :description="__('alerts.preferences.device_hint')">
        <div
            class="flex flex-wrap items-center gap-4"
            x-data="{
                state: 'loading',
                busy: false,
                async refresh() { this.state = window.dtpPush ? await window.dtpPush.state() : 'unsupported' },
                async enable() { this.busy = true; try { this.state = await window.dtpPush.enable() } catch (e) { this.state = 'error' } finally { this.busy = false } },
                async disable() { this.busy = true; try { this.state = await window.dtpPush.disable() } catch (e) { this.state = 'error' } finally { this.busy = false } },
            }"
            x-init="refresh()"
        >
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-300">
                <x-filament::icon icon="heroicon-o-bell-alert" class="size-5" />
            </span>

            <p class="min-w-0 flex-1 text-sm text-gray-600 dark:text-gray-300" aria-live="polite">
                <span x-show="state === 'loading'">{{ __('alerts.preferences.device.loading') }}</span>
                <span x-cloak x-show="state === 'on'" class="font-medium text-success-700 dark:text-success-400">{{ __('alerts.preferences.device.on') }}</span>
                <span x-cloak x-show="state === 'off'">{{ __('alerts.preferences.device.off') }}</span>
                <span x-cloak x-show="state === 'denied'">{{ __('alerts.preferences.device.denied') }}</span>
                <span x-cloak x-show="state === 'unsupported'">{{ __('alerts.preferences.device.unsupported') }}</span>
                <span x-cloak x-show="state === 'unconfigured'">{{ __('alerts.preferences.device.unconfigured') }}</span>
                <span x-cloak x-show="state === 'error'" class="text-danger-600 dark:text-danger-400">{{ __('alerts.preferences.device.error') }}</span>
            </p>

            <x-filament::button x-cloak x-show="state === 'off' || state === 'error'" x-on:click="enable()" x-bind:disabled="busy" icon="heroicon-m-bell">
                {{ __('alerts.preferences.device.enable') }}
            </x-filament::button>
            <x-filament::button x-cloak x-show="state === 'on'" x-on:click="disable()" x-bind:disabled="busy" color="gray" icon="heroicon-m-bell-slash">
                {{ __('alerts.preferences.device.disable') }}
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('alerts.preferences.channels_heading')" :description="__('alerts.preferences.channels_hint')">
        <ul role="list" class="-my-2 divide-y divide-gray-950/5 dark:divide-white/5">
            <li class="flex items-center gap-4 py-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('alerts.preferences.bell') }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('alerts.preferences.bell_hint') }}</p>
                </div>
                <x-filament::badge color="gray" size="sm">{{ __('alerts.preferences.always_on') }}</x-filament::badge>
            </li>

            @foreach (\App\Filament\Pages\AlertPreferences::CHANNELS as $channel)
                @php($on = $prefs[$channel] ?? false)
                <li class="flex items-center gap-4 py-4" wire:key="channel-{{ $channel }}">
                    <div class="min-w-0 flex-1">
                        <p id="alert-{{ $channel }}" class="text-sm font-medium text-gray-950 dark:text-white">{{ __('alerts.preferences.'.$channel) }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $channel === 'whatsapp' && ! $this->hasPhone() ? __('alerts.preferences.whatsapp_needs_phone') : __('alerts.preferences.'.$channel.'_hint') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-checked="{{ $on ? 'true' : 'false' }}"
                        aria-labelledby="alert-{{ $channel }}"
                        wire:click="toggle('{{ $channel }}')"
                        wire:loading.attr="disabled"
                        @class([
                            'relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-60',
                            'bg-primary-600' => $on,
                            'bg-gray-200 dark:bg-white/10' => ! $on,
                        ])
                    >
                        <span @class([
                            'inline-block size-5 rounded-full bg-white shadow-sm transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]',
                            'translate-x-6 rtl:-translate-x-6' => $on,
                            'translate-x-1 rtl:-translate-x-1' => ! $on,
                        ])></span>
                    </button>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
