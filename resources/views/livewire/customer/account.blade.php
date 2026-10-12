@php
    use App\Domain\Customers\Enums\VehicleType;
    use App\Support\Locales;
    $card = 'rounded-[1.75rem] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5';
    $core = 'rounded-[calc(1.75rem-0.375rem)] p-4';
    $field = 'w-full rounded-2xl border-0 bg-ivory-50 px-4 py-3 text-[0.9375rem] text-ink-950 ring-1 ring-ink-950/8 placeholder:text-ink-400 focus:ring-2 focus:ring-[var(--brand)]';
    $chip = 'rounded-full px-3 py-1.5 text-sm font-medium ring-1 transition active:scale-95';
@endphp
<div>
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" active="account">
        @if (! $customer)
            <section class="mx-auto max-w-md pt-8">
                <h1 class="display text-3xl text-ink-950">{{ __('guest.sign_in.title') }}</h1>
                <p class="mt-1.5 text-ink-500">{{ __('guest.sign_in.body', ['restaurant' => $restaurant->name]) }}</p>
                <div class="mt-6 {{ $card }}"><div class="{{ $core }}">@include('livewire.customer.partials.phone-sign-in')</div></div>
            </section>
        @else
            <section class="pt-4">
                <p class="text-sm font-medium text-ink-500"><bdi dir="ltr">{{ $customer->maskedPhone() }}</bdi></p>
                <h1 class="display mt-1 text-3xl text-ink-950">{{ $customer->name ?: __('guest.account.title') }}</h1>
                @if ($customer->orders_count > 0)
                    <p class="mt-1 text-sm text-ink-500">{{ trans_choice('guest.account.orders_count', $customer->orders_count, ['count' => $customer->orders_count]) }}</p>
                @endif
            </section>

            @if ($rewardsOn)
                <a href="{{ route('customer.rewards', $restaurant) }}" wire:navigate class="group mt-5 flex items-center gap-3 rounded-[1.75rem] bg-[var(--brand)] p-4 text-white shadow-[0_18px_40px_-18px_color-mix(in_oklab,var(--brand)_70%,transparent)] transition active:scale-[0.99]">
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-medium uppercase tracking-[0.16em] text-white/70 rtl:tracking-normal">{{ __('guest.rewards.title') }}</span>
                        <span class="mt-0.5 block text-lg font-semibold">{{ trans_choice('guest.savings.balance', $customer->points_balance, ['points' => number_format($customer->points_balance)]) }}@if ($customer->tier) · {{ $customer->tier->name }}@endif</span>
                    </span>
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white/15 transition group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" aria-hidden="true">
                        <svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </a>
            @endif

            {{-- Profile --}}
            <form wire:submit="saveProfile" class="mt-6 {{ $card }}" aria-labelledby="profile-title">
                <div class="{{ $core }} space-y-4">
                    <h2 id="profile-title" class="font-semibold text-ink-950">{{ __('guest.account.profile') }}</h2>
                    <label class="block">
                        <span class="text-sm font-medium text-ink-700">{{ __('guest.checkout.name') }}</span>
                        <input wire:model="name" type="text" autocomplete="given-name" maxlength="60" class="mt-1.5 {{ $field }}">
                        @error('name')<span class="mt-1 block text-sm text-status-delayed">{{ $message }}</span>@enderror
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-sm font-medium text-ink-700">{{ __('guest.account.birthday') }}</span>
                            <input wire:model="birthday" type="date" max="{{ now()->subDay()->format('Y-m-d') }}" dir="ltr" class="mt-1.5 {{ $field }}">
                            @error('birthday')<span class="mt-1 block text-sm text-status-delayed">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink-700">{{ __('guest.account.language') }}</span>
                            <select wire:model="locale" class="mt-1.5 {{ $field }}">
                                @foreach ($locales as $code)
                                    <option value="{{ $code }}">{{ Locales::native($code) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <p class="text-xs text-ink-400">{{ __('guest.account.birthday_hint') }}</p>
                    <fieldset class="space-y-2 rounded-2xl bg-ivory-50 p-3 ring-1 ring-ink-950/5">
                        <legend class="px-1 text-sm font-medium text-ink-700">{{ __('guest.account.messages') }}</legend>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model="orderUpdates" class="mt-0.5 size-5 rounded border-ink-950/20 text-[var(--brand)] focus:ring-[var(--brand)]">
                            <span><span class="block text-sm font-medium text-ink-900">{{ __('guest.account.order_updates') }}</span><span class="block text-xs text-ink-500">{{ __('guest.account.order_updates_hint') }}</span></span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model="receipts" class="mt-0.5 size-5 rounded border-ink-950/20 text-[var(--brand)] focus:ring-[var(--brand)]">
                            <span><span class="block text-sm font-medium text-ink-900">{{ __('guest.account.receipts') }}</span><span class="block text-xs text-ink-500">{{ __('guest.account.receipts_hint') }}</span></span>
                        </label>
                    </fieldset>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveProfile" class="w-full rounded-full bg-ink-950 py-3 font-semibold text-white transition active:scale-[0.98] disabled:opacity-70">{{ __('guest.account.save') }}</button>
                </div>
            </form>

            {{-- Cars --}}
            <section class="mt-6 {{ $card }}" aria-labelledby="cars-title">
                <div class="{{ $core }}">
                    <div class="flex items-center justify-between">
                        <h2 id="cars-title" class="font-semibold text-ink-950">{{ __('guest.account.cars') }}</h2>
                        @unless ($addingVehicle)
                            <button type="button" wire:click="$set('addingVehicle', true)" class="text-sm font-semibold text-[var(--brand)]">+ {{ __('guest.checkout.add_car') }}</button>
                        @endunless
                    </div>
                    @if ($vehicles->isEmpty() && ! $addingVehicle)
                        <p class="mt-2 text-sm text-ink-500">{{ __('guest.account.no_cars') }}</p>
                    @endif
                    <ul class="mt-2 divide-y divide-ink-950/5">
                        @foreach ($vehicles as $vehicle)
                            <li class="flex items-center gap-3 py-3" wire:key="car-{{ $vehicle->id }}">
                                <span class="text-xl" aria-hidden="true">{{ $vehicle->type->symbol() }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-ink-900"><bdi>{{ $vehicle->describe() }}</bdi></span>
                                    @if ($vehicle->is_default)<span class="text-xs font-semibold text-teal-800">{{ __('guest.account.default_car') }}</span>@endif
                                </span>
                                @unless ($vehicle->is_default)
                                    <button type="button" wire:click="makeDefault({{ $vehicle->id }})" class="text-xs font-semibold text-ink-600">{{ __('guest.account.make_default') }}</button>
                                @endunless
                                <button type="button" wire:click="deleteVehicle({{ $vehicle->id }})" wire:confirm="{{ __('guest.account.remove_car_confirm') }}" class="grid size-8 place-items-center rounded-full text-ink-400 hover:bg-ivory-100 hover:text-ink-800" aria-label="{{ __('guest.checkout.remove') }}">
                                    <svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    @if ($addingVehicle)
                        <form wire:submit="addVehicle" class="mt-2 space-y-3 rounded-2xl bg-ivory-50 p-3 ring-1 ring-ink-950/5">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach (VehicleType::cases() as $type)
                                    <button type="button" wire:click="$set('vehicleType', '{{ $type->value }}')" @class([$chip, 'bg-[var(--brand)] text-white ring-[var(--brand)]' => $vehicleType === $type->value, 'bg-white text-ink-700 ring-ink-950/10' => $vehicleType !== $type->value])>
                                        <span aria-hidden="true">{{ $type->symbol() }}</span> {{ $type->getLabel() }}
                                    </button>
                                @endforeach
                            </div>
                            <input wire:model="plate" type="text" maxlength="20" autocapitalize="characters" dir="ltr" placeholder="{{ __('guest.checkout.plate') }}" aria-label="{{ __('guest.checkout.plate') }}" class="{{ $field }} bg-white text-center text-lg font-semibold uppercase tracking-widest">
                            @error('plate')<span class="block text-sm text-status-delayed">{{ $message }}</span>@enderror
                            <div class="grid grid-cols-2 gap-2">
                                <input wire:model="color" type="text" maxlength="30" placeholder="{{ __('guest.checkout.color') }}" aria-label="{{ __('guest.checkout.color') }}" class="{{ $field }} bg-white">
                                <input wire:model="make" type="text" maxlength="40" placeholder="{{ __('guest.checkout.make') }}" aria-label="{{ __('guest.checkout.make') }}" class="{{ $field }} bg-white">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="$set('addingVehicle', false)" class="flex-1 rounded-full bg-white py-2.5 text-sm font-semibold text-ink-700 ring-1 ring-ink-950/10">{{ __('guest.cancel') }}</button>
                                <button type="submit" class="flex-1 rounded-full bg-ink-950 py-2.5 text-sm font-semibold text-white">{{ __('guest.account.save_car') }}</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>

            {{-- Favourites --}}
            <section class="mt-6 {{ $card }}" aria-labelledby="fav-title">
                <div class="{{ $core }}">
                    <h2 id="fav-title" class="font-semibold text-ink-950">{{ __('guest.home.favorites') }}</h2>
                    @if ($favorites->isEmpty())
                        <p class="mt-2 text-sm text-ink-500">{{ __('guest.account.no_favorites') }}</p>
                    @else
                        <ul class="mt-2 divide-y divide-ink-950/5">
                            @foreach ($favorites as $product)
                                <li class="flex items-center gap-3 py-2.5" wire:key="fav-{{ $product->id }}">
                                    <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-ivory-100">
                                        @if ($image = $product->imageUrl(200))<img src="{{ $image }}" alt="" class="size-full object-cover">@else<span aria-hidden="true">{{ $product->category?->icon ?: '🍽️' }}</span>@endif
                                    </span>
                                    <span class="flex-1 font-medium text-ink-900">{{ $product->name }}</span>
                                    <button type="button" wire:click="removeFavorite({{ $product->id }})" class="text-xs font-semibold text-ink-500">{{ __('guest.checkout.remove') }}</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Devices --}}
            <section class="mt-6 {{ $card }}" aria-labelledby="devices-title">
                <div class="{{ $core }}">
                    <h2 id="devices-title" class="font-semibold text-ink-950">{{ __('guest.account.devices') }}</h2>
                    <ul class="mt-2 divide-y divide-ink-950/5">
                        @foreach ($devices as $device)
                            <li class="flex items-center gap-3 py-2.5" wire:key="dev-{{ $device->id }}">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-ink-900">{{ $device->deviceLabel() }}</span>
                                    <span class="block text-xs text-ink-500">
                                        @if ($device->token_hash === $current)
                                            {{ __('guest.account.this_device') }}
                                        @else
                                            {{ __('guest.account.last_active', ['time' => $device->last_active_at?->diffForHumans() ?? '—']) }}
                                        @endif
                                    </span>
                                </span>
                                <button type="button" wire:click="revokeDevice({{ $device->id }})" class="text-xs font-semibold text-ink-500 hover:text-ink-900">{{ __('guest.account.sign_out_device') }}</button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <button type="button" wire:click="signOut" class="mt-6 w-full rounded-full bg-white py-3.5 font-semibold text-ink-800 ring-1 ring-ink-950/10 transition active:scale-[0.98]">{{ __('guest.account.sign_out') }}</button>
        @endif
    </x-customer.shell>
</div>
