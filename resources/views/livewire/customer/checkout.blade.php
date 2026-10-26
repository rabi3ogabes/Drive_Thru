@php
    use App\Domain\Customers\Enums\VehicleType;
    use App\Support\Money;
    $currency = $restaurant->currency;
    $open = $state->acceptsOrders();
    $card = 'rounded-[1.75rem] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5';
    $core = 'rounded-[calc(1.75rem-0.375rem)] p-4';
    $chip = 'rounded-full px-4 py-2 text-sm font-medium ring-1 transition active:scale-95';
    $chipOn = 'bg-[var(--brand)] text-white ring-[var(--brand)]';
    $chipOff = 'bg-white text-ink-700 ring-ink-950/10';
    $field = 'w-full rounded-2xl border-0 bg-ivory-50 px-4 py-3 text-[0.9375rem] text-ink-950 ring-1 ring-ink-950/8 placeholder:text-ink-400 focus:ring-2 focus:ring-[var(--brand)]';
    $menuUrl = route('public.branch', [$restaurant, $branch->code]);
@endphp
<div>
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" :menu-url="$menuUrl" bare>
        <section class="pt-3">
            <a href="{{ $menuUrl }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-ink-500">
                <svg class="flip-rtl size-4 rotate-180" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ __('guest.checkout.back_to_menu') }}
            </a>
            <h1 class="display mt-1 text-3xl text-ink-950">{{ __('guest.checkout.title') }}</h1>
            <p class="mt-1 text-sm text-ink-500">{{ __('guest.checkout.pickup_at', ['branch' => $branch->name]) }}</p>
        </section>

        @if ($bag === null || $bag->isEmpty())
            {{-- Empty bag --}}
            <div class="mt-10 rounded-[2rem] bg-white px-6 py-12 text-center shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5">
                <div class="mx-auto grid size-16 place-items-center rounded-full bg-ivory-100 text-3xl" aria-hidden="true">🛍️</div>
                <h2 class="mt-4 text-lg font-semibold text-ink-950">{{ __('guest.checkout.empty_title') }}</h2>
                <p class="mt-1 text-ink-500">{{ __('guest.checkout.empty_body') }}</p>
                <a href="{{ $menuUrl }}" wire:navigate class="mt-6 inline-flex rounded-full bg-[var(--brand)] px-6 py-3 font-semibold text-white">{{ __('guest.checkout.browse') }}</a>
            </div>
        @else
            @unless ($open)
                <p class="mt-4 rounded-2xl bg-brass-50 px-4 py-3 text-sm text-brass-900 ring-1 ring-brass-500/15">
                    {{ $opensAt ? __('guest.checkout.closed_until', ['time' => $opensAt->format('H:i')]) : __('guest.checkout.closed') }}
                </p>
            @endunless

            {{-- Bag --}}
            <section class="mt-5 {{ $card }}" aria-labelledby="bag-title">
                <div class="{{ $core }}">
                    <div class="flex items-baseline justify-between">
                        <h2 id="bag-title" class="font-semibold text-ink-950">{{ __('guest.checkout.bag', ['count' => $bag->count()]) }}</h2>
                        <a href="{{ $menuUrl }}" wire:navigate class="text-sm font-medium text-[var(--brand)]">{{ __('guest.checkout.add_more') }}</a>
                    </div>
                    <ul class="mt-3 divide-y divide-ink-950/5">
                        @foreach ($bag->lines as $line)
                            <li class="flex gap-3 py-3" wire:key="line-{{ $line->key }}">
                                <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-ivory-100 text-xl">
                                    @if ($line->product && ($image = $line->product->imageUrl(200)))
                                        <img src="{{ $image }}" alt="" class="size-full object-cover">
                                    @else
                                        <span aria-hidden="true">🍽️</span>
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="font-medium leading-snug text-ink-950">{{ $line->product?->name ?? __('guest.checkout.unavailable_item') }}</p>
                                        <p class="shrink-0 text-sm font-semibold tabular-nums text-ink-950" dir="ltr">{{ Money::format($line->totalMinor(), $currency) }}</p>
                                    </div>
                                    @if ($line->options !== [])
                                        <p class="mt-0.5 text-[0.8125rem] leading-relaxed text-ink-500">
                                            {{ collect($line->options)->map(fn ($o) => $o['option'][app()->getLocale()] ?? collect($o['option'])->first())->join('، ') }}
                                        </p>
                                    @endif
                                    @if ($line->notes)<p class="mt-0.5 text-[0.8125rem] italic text-ink-500">“{{ $line->notes }}”</p>@endif
                                    @if ($line->error)<p class="mt-1 text-[0.8125rem] font-medium text-status-delayed" role="alert">{{ $line->error }}</p>@endif
                                    <div class="mt-2 flex items-center gap-3">
                                        <div class="flex items-center rounded-full bg-ivory-100 p-0.5">
                                            <button type="button" wire:click="setQuantity('{{ $line->key }}', {{ $line->quantity - 1 }})" class="grid size-8 place-items-center rounded-full text-ink-800 active:bg-white" aria-label="{{ __('guest.menu.less') }}">
                                                @if ($line->quantity <= 1)
                                                    <svg class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                @else
                                                    <svg class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                @endif
                                            </button>
                                            <span class="w-6 text-center text-sm font-semibold tabular-nums">{{ $line->quantity }}</span>
                                            <button type="button" wire:click="setQuantity('{{ $line->key }}', {{ $line->quantity + 1 }})" @disabled($line->quantity >= 20) class="grid size-8 place-items-center rounded-full text-ink-800 active:bg-white disabled:opacity-35" aria-label="{{ __('guest.menu.more') }}">
                                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                            </button>
                                        </div>
                                        @if (! $line->isValid())
                                            <button type="button" wire:click="remove('{{ $line->key }}')" class="text-sm font-medium text-ink-500 underline underline-offset-2">{{ __('guest.checkout.remove') }}</button>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Goes well with --}}
            @if ($suggestions !== [])
                <section class="mt-6" aria-labelledby="goes-title">
                    <h2 id="goes-title" class="font-semibold text-ink-950">{{ __('guest.checkout.goes_well') }}</h2>
                    <ul class="-mx-4 mt-3 flex gap-3 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:-mx-6 sm:px-6">
                        @foreach ($suggestions as $suggestion)
                            <li class="w-36 shrink-0" wire:key="sg-{{ $suggestion['id'] }}">
                                <button type="button" wire:click="addSuggestion({{ $suggestion['id'] }})" class="block w-full overflow-hidden rounded-[1.35rem] bg-white text-start shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5 transition active:scale-[0.98]">
                                    <span class="grid aspect-[4/3] place-items-center bg-ivory-100 text-3xl">
                                        @if ($suggestion['image'])<img src="{{ $suggestion['image'] }}" alt="" loading="lazy" class="size-full object-cover">@else<span aria-hidden="true">🍽️</span>@endif
                                    </span>
                                    <span class="block p-2.5">
                                        <span class="line-clamp-1 text-sm font-medium text-ink-950">{{ $suggestion['name'] }}</span>
                                        <span class="mt-0.5 flex items-center justify-between">
                                            <span class="text-sm font-semibold tabular-nums text-[var(--brand)]" dir="ltr">{{ $suggestion['price'] }}</span>
                                            <span class="grid size-6 place-items-center rounded-full bg-[var(--brand)] text-white" aria-hidden="true"><svg class="size-3" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                                        </span>
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! $customer)
                {{-- First time here: one code and we're done --}}
                <section class="mt-6 {{ $card }}" aria-labelledby="who-title">
                    <div class="{{ $core }} bg-gradient-to-br from-ivory-100 to-white">
                        <h2 id="who-title" class="font-semibold text-ink-950">{{ __('guest.checkout.who') }}</h2>
                        <p class="mt-0.5 text-sm text-ink-500">{{ __('guest.checkout.who_hint') }}</p>
                        <div class="mt-4">@include('livewire.customer.partials.phone-sign-in')</div>
                    </div>
                </section>
            @else
                {{-- Name + car --}}
                <section class="mt-6 {{ $card }}" aria-labelledby="car-title">
                    <div class="{{ $core }} space-y-5">
                        <label class="block">
                            <span class="text-sm font-medium text-ink-700">{{ __('guest.checkout.name') }}</span>
                            <input wire:model="name" type="text" autocomplete="given-name" maxlength="60" class="mt-1.5 {{ $field }}" placeholder="{{ __('guest.checkout.name_hint') }}">
                            @error('name')<span class="mt-1 block text-sm text-status-delayed">{{ $message }}</span>@enderror
                        </label>

                        <div>
                            <h2 id="car-title" class="text-sm font-medium text-ink-700">{{ __('guest.checkout.car') }}</h2>
                            @if ($vehicles->isNotEmpty())
                                <div class="mt-2 grid gap-2">
                                    @foreach ($vehicles as $vehicle)
                                        <button type="button" wire:key="v-{{ $vehicle->id }}" wire:click="$set('vehicleId', {{ $vehicle->id }}); $set('addingVehicle', false)"
                                                @class(['flex items-center gap-3 rounded-2xl px-4 py-3 text-start ring-1 transition',
                                                        'bg-[color-mix(in_oklab,var(--brand)_7%,white)] ring-2 ring-[var(--brand)]' => ! $addingVehicle && $vehicleId === $vehicle->id,
                                                        'ring-ink-950/8' => $addingVehicle || $vehicleId !== $vehicle->id])>
                                            <span class="text-xl" aria-hidden="true">{{ $vehicle->type->symbol() }}</span>
                                            <span class="flex-1 font-medium text-ink-900"><bdi>{{ $vehicle->describe() }}</bdi></span>
                                        </button>
                                    @endforeach
                                    @unless ($addingVehicle)
                                        <button type="button" wire:click="$set('addingVehicle', true)" class="rounded-2xl px-4 py-3 text-start text-sm font-medium text-[var(--brand)] border border-dashed border-ink-950/15">+ {{ __('guest.checkout.add_car') }}</button>
                                    @endunless
                                </div>
                            @endif
                            @error('vehicleId')<span class="mt-1 block text-sm text-status-delayed">{{ __('guest.checkout.pick_car') }}</span>@enderror

                            @if ($addingVehicle)
                                <div class="mt-2 space-y-3 rounded-2xl bg-ivory-50 p-3 ring-1 ring-ink-950/5">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach (VehicleType::cases() as $type)
                                            <button type="button" wire:click="$set('vehicleType', '{{ $type->value }}')" @class([$chip, 'py-1.5 px-3', $chipOn => $vehicleType === $type->value, $chipOff => $vehicleType !== $type->value])>
                                                <span aria-hidden="true">{{ $type->symbol() }}</span> {{ $type->getLabel() }}
                                            </button>
                                        @endforeach
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="col-span-2 block">
                                            <span class="sr-only">{{ __('guest.checkout.plate') }}</span>
                                            <input wire:model="plate" type="text" maxlength="20" autocapitalize="characters" dir="ltr" placeholder="{{ __('guest.checkout.plate') }}" class="{{ $field }} bg-white text-center text-lg font-semibold uppercase tracking-widest">
                                            @error('plate')<span class="mt-1 block text-sm text-status-delayed">{{ $message }}</span>@enderror
                                        </label>
                                        <input wire:model="color" type="text" maxlength="30" placeholder="{{ __('guest.checkout.color') }}" aria-label="{{ __('guest.checkout.color') }}" class="{{ $field }} bg-white">
                                        <input wire:model="make" type="text" maxlength="40" placeholder="{{ __('guest.checkout.make') }}" aria-label="{{ __('guest.checkout.make') }}" class="{{ $field }} bg-white">
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($lanes->count() > 1)
                            <div>
                                <h2 class="text-sm font-medium text-ink-700">{{ __('guest.checkout.lane') }}</h2>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" wire:click="$set('laneId', null)" @class([$chip, $chipOn => $laneId === null, $chipOff => $laneId !== null])>{{ __('guest.checkout.any_lane') }}</button>
                                    @foreach ($lanes as $lane)
                                        <button type="button" wire:key="lane-{{ $lane->id }}" wire:click="$set('laneId', {{ $lane->id }})" @class([$chip, $chipOn => $laneId === $lane->id, $chipOff => $laneId !== $lane->id])>{{ $lane->name }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- When --}}
                <section class="mt-6 {{ $card }}" aria-labelledby="when-title">
                    <div class="{{ $core }}">
                        <h2 id="when-title" class="font-semibold text-ink-950">{{ __('guest.checkout.when') }}</h2>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            @foreach (['now', 'delay', 'on_the_way', 'scheduled'] as $option)
                                @php $disabled = ! $open && $option !== 'scheduled'; @endphp
                                <button type="button" wire:click="$set('timing', '{{ $option }}')" @disabled($disabled)
                                        @class(['rounded-2xl px-3 py-3 text-start ring-1 transition disabled:opacity-40',
                                                'bg-[color-mix(in_oklab,var(--brand)_7%,white)] ring-2 ring-[var(--brand)]' => $timing === $option,
                                                'ring-ink-950/8' => $timing !== $option])>
                                    <span class="block text-sm font-semibold text-ink-950">{{ __('guest.timing.'.$option) }}</span>
                                    <span class="mt-0.5 block text-xs leading-snug text-ink-500">{{ __('guest.timing.'.$option.'_hint') }}</span>
                                </button>
                            @endforeach
                        </div>

                        @if ($timing === 'delay')
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($delays as $minutes)
                                    <button type="button" wire:click="$set('delay', {{ $minutes }})" @class([$chip, 'tabular-nums', $chipOn => $delay === $minutes, $chipOff => $delay !== $minutes])>{{ __('guest.timing.in_minutes', ['minutes' => $minutes]) }}</button>
                                @endforeach
                            </div>
                        @elseif ($timing === 'scheduled')
                            <label class="mt-3 block">
                                <span class="sr-only">{{ __('guest.timing.scheduled') }}</span>
                                <input wire:model.live="scheduledAt" type="datetime-local" min="{{ $minSchedule }}" max="{{ $maxSchedule }}" step="900" dir="ltr" class="{{ $field }}">
                                @error('scheduledAt')<span class="mt-1 block text-sm text-status-delayed">{{ $message }}</span>@enderror
                            </label>
                        @endif

                        <p class="mt-3 flex items-center gap-2 text-sm text-ink-600">
                            <span class="size-1.5 rounded-full bg-status-ready"></span>
                            @if ($timing === 'now')
                                {{ __('guest.checkout.ready_by', ['time' => $readyBy?->format('H:i'), 'minutes' => $bag->prepMinutes]) }}
                            @elseif ($timing === 'delay')
                                {{ __('guest.checkout.ready_by', ['time' => $readyBy?->addMinutes($delay)->format('H:i'), 'minutes' => $bag->prepMinutes + $delay]) }}
                            @elseif ($timing === 'on_the_way')
                                {{ __('guest.checkout.on_the_way_note', ['minutes' => $bag->prepMinutes]) }}
                            @else
                                {{ __('guest.checkout.scheduled_note') }}
                            @endif
                        </p>
                    </div>
                </section>

                {{-- Payment --}}
                <section class="mt-6 {{ $card }}" aria-labelledby="pay-title">
                    <div class="{{ $core }}">
                        <h2 id="pay-title" class="font-semibold text-ink-950">{{ __('guest.checkout.payment') }}</h2>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            @foreach ($methods as $method)
                                <button type="button" wire:click="$set('payment', '{{ $method->value }}')"
                                        @class(['flex items-center gap-2.5 rounded-2xl px-3 py-3 text-start ring-1 transition',
                                                'bg-[color-mix(in_oklab,var(--brand)_7%,white)] ring-2 ring-[var(--brand)]' => $payment === $method->value,
                                                'ring-ink-950/8' => $payment !== $method->value])>
                                    <x-filament::icon :icon="$method->getIcon()" class="size-5 text-ink-600" />
                                    <span class="text-sm font-semibold text-ink-950">{{ $method->getLabel() }}</span>
                                </button>
                            @endforeach
                        </div>
                        <label class="mt-4 block">
                            <span class="text-sm font-medium text-ink-700">{{ __('guest.checkout.notes') }}</span>
                            <textarea wire:model="notes" rows="2" maxlength="300" placeholder="{{ __('guest.checkout.notes_hint') }}" class="mt-1.5 resize-none {{ $field }}"></textarea>
                        </label>
                        @unless ($customer->acceptsOffers())
                            <label class="mt-4 flex items-start gap-3 rounded-2xl bg-ivory-50 p-3 ring-1 ring-ink-950/5">
                                <input type="checkbox" wire:model="offers" class="mt-0.5 size-5 rounded border-ink-950/20 text-[var(--brand)] focus:ring-[var(--brand)]">
                                <span><span class="block text-sm font-medium text-ink-900">{{ __('guest.offers.opt_in') }}</span><span class="block text-xs text-ink-500">{{ __('guest.offers.opt_in_hint') }}</span></span>
                            </label>
                        @endunless
                    </div>
                </section>
            @endif

            {{-- Savings: code, wallet reward, points (all priced by the server) --}}
            @if ($savings['codes'] || $savings['loyalty'])
                <section class="mt-6 {{ $card }}" aria-labelledby="save-title">
                    <div class="{{ $core }} space-y-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <h2 id="save-title" class="font-semibold text-ink-950">{{ __('guest.savings.title') }}</h2>
                            @if ($savings['loyalty'])
                                <a href="{{ route('customer.rewards', $restaurant) }}" wire:navigate class="text-sm font-medium text-[var(--brand)]">
                                    {{ trans_choice('guest.savings.balance', $savings['balance'], ['points' => number_format($savings['balance'])]) }}
                                </a>
                            @endif
                        </div>

                        @if ($savings['codes'])
                            @if ($couponCode)
                                <div class="flex items-center justify-between gap-3 rounded-2xl bg-[color-mix(in_oklab,var(--brand)_7%,white)] px-4 py-3 ring-1 ring-[var(--brand)]/20">
                                    <p class="text-sm"><span class="font-semibold tracking-wide text-ink-950" dir="ltr">{{ $couponCode }}</span> <span class="text-ink-600">· {{ __('guest.savings.code_applied') }}</span></p>
                                    <button type="button" wire:click="removeCoupon" class="min-h-11 shrink-0 px-2 text-sm font-medium text-ink-500">{{ __('guest.savings.remove') }}</button>
                                </div>
                            @else
                                <form wire:submit="applyCoupon" class="flex gap-2">
                                    <label class="min-w-0 flex-1">
                                        <span class="sr-only">{{ __('guest.savings.code') }}</span>
                                        <input wire:model="couponInput" type="text" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" dir="ltr"
                                               placeholder="{{ __('guest.savings.code_placeholder') }}" class="{{ $field }} uppercase placeholder:normal-case">
                                    </label>
                                    <button type="submit" wire:loading.attr="disabled" wire:target="applyCoupon" class="min-h-12 shrink-0 rounded-2xl bg-ink-950 px-5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">{{ __('guest.savings.apply') }}</button>
                                </form>
                            @endif
                            @if ($couponError)
                                <p class="text-sm text-status-delayed" role="alert">{{ $couponError }}</p>
                            @endif
                        @endif

                        @if ($savings['loyalty'] && $savings['wallet']->isNotEmpty())
                            <div>
                                <p class="text-sm font-medium text-ink-700">{{ __('guest.savings.use_reward') }}</p>
                                <div class="mt-2 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]">
                                    @foreach ($savings['wallet'] as $item)
                                        <button type="button" wire:click="chooseReward({{ $walletRewardId === $item->id ? 'null' : $item->id }})" aria-pressed="{{ $walletRewardId === $item->id ? 'true' : 'false' }}"
                                                @class(['shrink-0 rounded-2xl px-4 py-2.5 text-start ring-1 transition active:scale-95',
                                                        'bg-[var(--brand)] text-white ring-[var(--brand)]' => $walletRewardId === $item->id,
                                                        'bg-white text-ink-800 ring-ink-950/10' => $walletRewardId !== $item->id])>
                                            <span class="block text-sm font-semibold">{{ $item->reward->name }}</span>
                                            @if ($item->expires_at)
                                                <span @class(['block text-xs', 'text-white/75' => $walletRewardId === $item->id, 'text-ink-500' => $walletRewardId !== $item->id])>{{ __('guest.savings.until', ['date' => $item->expires_at->setTimezone($restaurant->timezone)->format('d/m')]) }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($savings['loyalty'] && ($savings['pointsOffer']['points'] > 0 || $usePoints))
                            <button type="button" wire:click="togglePoints" role="switch" aria-checked="{{ $usePoints ? 'true' : 'false' }}"
                                    class="flex w-full items-center justify-between gap-3 rounded-2xl bg-ivory-50 px-4 py-3 text-start ring-1 ring-ink-950/8">
                                <span>
                                    <span class="block text-sm font-semibold text-ink-950">{{ __('guest.savings.use_points', ['points' => number_format($savings['pointsOffer']['points'])]) }}</span>
                                    <span class="block text-xs text-ink-500" dir="ltr">−{{ Money::format($savings['pointsOffer']['discountMinor'], $currency) }}</span>
                                </span>
                                <span @class(['relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]', 'bg-[var(--brand)]' => $usePoints, 'bg-ink-200' => ! $usePoints])>
                                    <span @class(['inline-block size-5 rounded-full bg-white shadow-sm transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]', 'translate-x-6 rtl:-translate-x-6' => $usePoints, 'translate-x-1 rtl:-translate-x-1' => ! $usePoints])></span>
                                </span>
                            </button>
                        @endif

                        @if ($bag->benefitError && ! $couponError)
                            <p class="text-sm text-status-delayed" role="alert">{{ $bag->benefitError }}</p>
                        @endif
                    </div>
                </section>
            @endif

            {{-- Totals (the server's numbers) --}}
            <section class="mt-6 px-2" aria-label="{{ __('guest.checkout.summary') }}">
                <dl class="space-y-1.5 text-sm tabular-nums">
                    <div class="flex justify-between text-ink-600"><dt>{{ __('guest.checkout.subtotal') }}</dt><dd dir="ltr">{{ Money::format($bag->subtotalMinor, $currency) }}</dd></div>
                    @foreach ($bag->adjustments as $adjustment)
                        @continue($adjustment->amountMinor === 0)
                        <div class="flex justify-between gap-3 text-teal-800"><dt class="min-w-0 truncate">{{ $adjustment->localLabel() }}</dt><dd dir="ltr">−{{ Money::format($adjustment->amountMinor, $currency) }}</dd></div>
                    @endforeach
                    @if ($bag->serviceFeeMinor > 0)
                        <div class="flex justify-between text-ink-600"><dt>{{ __('guest.checkout.service_fee') }}</dt><dd dir="ltr">{{ Money::format($bag->serviceFeeMinor, $currency) }}</dd></div>
                    @endif
                    @if ($bag->taxMinor > 0)
                        <div class="flex justify-between text-ink-600"><dt>{{ __('guest.checkout.tax') }}</dt><dd dir="ltr">{{ Money::format($bag->taxMinor, $currency) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-ink-950/8 pt-2.5 text-base font-semibold text-ink-950"><dt>{{ __('guest.checkout.total') }}</dt><dd dir="ltr" data-total>{{ Money::format($bag->totalMinor, $currency) }}</dd></div>
                </dl>
                @foreach ($stamps as $stamp)
                    <p class="mt-2 flex items-center gap-1.5 text-xs text-brass-800">
                        <svg class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.2"/><path d="m5.3 8.2 1.8 1.8 3.6-3.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ $stamp['fills'] ? __('guest.savings.stamps_fill', ['card' => $stamp['program']->name, 'reward' => $stamp['program']->reward->name]) : trans_choice('guest.savings.stamps_add', $stamp['adds'], ['count' => $stamp['adds'], 'card' => $stamp['program']->name, 'left' => $stamp['left']]) }}
                    </p>
                @endforeach
                @if ($bag->pointsToEarn > 0)
                    <p class="mt-2 flex items-center gap-1.5 text-xs text-brass-800">
                        <svg class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.3l-3.8 2 .7-4.3-3.1-3 4.3-.6L8 1.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
                        {{ __('guest.savings.earn', ['points' => number_format($bag->pointsToEarn)]) }}
                    </p>
                @endif
            </section>

            @if ($problem)
                <div class="mt-5 rounded-2xl bg-white px-4 py-3 text-sm text-ink-900 ring-1 ring-status-delayed/30" role="alert">
                    <p>{{ $problem }}</p>
                    @if ($suggestedAt)
                        <button type="button" wire:click="useSuggestedTime" class="mt-2 font-semibold text-[var(--brand)]">{{ __('guest.checkout.use_time', ['time' => substr($suggestedAt, 11)]) }}</button>
                    @endif
                </div>
            @endif

            <div class="h-28" aria-hidden="true"></div>

            {{-- Place order --}}
            <div class="fixed inset-x-0 bottom-0 z-40 mx-auto max-w-2xl bg-gradient-to-t from-ivory-50 via-ivory-50/95 to-transparent px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-6 sm:px-6">
                <button type="button" wire:click="place" wire:loading.attr="disabled" wire:target="place"
                        @disabled(! $customer || $bag->hasProblems())
                        class="group flex w-full items-center justify-between gap-3 rounded-full bg-[var(--brand)] py-2 pe-2 ps-6 text-white shadow-[0_18px_40px_-14px_color-mix(in_oklab,var(--brand)_70%,transparent)] transition active:scale-[0.985] disabled:bg-ink-300 disabled:shadow-none">
                    <span class="font-semibold">
                        <span wire:loading.remove wire:target="place">{{ $customer ? __('guest.checkout.place') : __('guest.checkout.sign_in_to_place') }}</span>
                        <span wire:loading wire:target="place">{{ __('guest.checkout.placing') }}</span>
                    </span>
                    <span class="rounded-full bg-white/15 px-4 py-2.5 text-sm font-semibold tabular-nums" dir="ltr">{{ Money::format($bag->totalMinor, $currency) }}</span>
                </button>
            </div>
        @endif
    </x-customer.shell>
</div>
