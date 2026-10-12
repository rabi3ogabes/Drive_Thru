@php
    use App\Domain\Loyalty\Enums\LedgerType;
    use App\Domain\Loyalty\Enums\RewardType;
    use App\Support\Money;
    $card = 'rounded-[1.75rem] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5';
    $core = 'rounded-[calc(1.75rem-0.375rem)] p-4';
    $tz = $restaurant->timezone;
    $currency = $restaurant->currency;
    $describe = fn ($reward): string => match ($reward->type) {
        RewardType::FreeItem => __('guest.rewards.gives.free_item', ['item' => $reward->product?->name ?? '']),
        RewardType::PercentOff => __('guest.rewards.gives.percent_off', ['percent' => rtrim(rtrim(number_format(($reward->value ?? 0) / 100, 2), '0'), '.')]),
        RewardType::FixedOff => __('guest.rewards.gives.fixed_off', ['amount' => Money::format((int) $reward->value, $currency)]),
        RewardType::BonusPoints => __('guest.rewards.gives.bonus_points', ['points' => number_format((int) $reward->value)]),
    };
@endphp
<div>
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" active="account">
        <section class="pt-4">
            <h1 class="display text-3xl text-ink-950">{{ __('guest.rewards.title') }}</h1>
            <p class="mt-1 text-sm text-ink-500">{{ __('guest.rewards.subtitle', ['points' => number_format((int) $program->earn_points_per_unit), 'currency' => Money::symbol($currency)]) }}</p>
        </section>

        @if (! $customer)
            <section class="mt-6 {{ $card }}">
                <div class="{{ $core }}">
                    <h2 class="font-semibold text-ink-950">{{ __('guest.rewards.sign_in_title') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ __('guest.rewards.sign_in_body') }}</p>
                    <div class="mt-4">@include('livewire.customer.partials.phone-sign-in')</div>
                </div>
            </section>
        @else
            {{-- Balance and tier --}}
            <section class="mt-6 rounded-[2rem] bg-[color-mix(in_oklab,var(--brand)_12%,white)] p-1.5 ring-1 ring-[var(--brand)]/15" aria-labelledby="balance-title">
                <div class="relative overflow-hidden rounded-[calc(2rem-0.375rem)] bg-[var(--brand)] p-5 text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.18)]">
                    <div aria-hidden="true" class="pointer-events-none absolute -end-16 -top-20 size-56 rounded-full bg-white/10 blur-2xl"></div>
                    <p id="balance-title" class="text-xs font-medium uppercase tracking-[0.18em] text-white/70 rtl:tracking-normal">{{ __('guest.rewards.balance') }}</p>
                    <p class="mt-1 text-5xl font-semibold tabular-nums tracking-tight" dir="ltr">{{ number_format($customer->points_balance) }}</p>
                    <p class="mt-1 text-sm text-white/75" dir="ltr">≈ {{ Money::format($program->valueOf($customer->points_balance), $currency) }}</p>

                    @if ($progress && $tiers->isNotEmpty())
                        <div class="mt-5 rounded-2xl bg-white/10 p-3.5 ring-1 ring-white/15">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-semibold">{{ $progress['tier']?->name ?? __('guest.rewards.no_tier') }}</span>
                                @if ($progress['next'])
                                    <span class="text-white/75">{{ __('guest.rewards.to_next', ['points' => number_format($progress['needed']), 'tier' => $progress['next']->name]) }}</span>
                                @else
                                    <span class="text-white/75">{{ __('guest.rewards.top_tier') }}</span>
                                @endif
                            </div>
                            <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-white/20" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percent'] }}" aria-label="{{ __('guest.rewards.progress') }}">
                                <div class="h-full origin-[left] rounded-full bg-white transition-transform duration-1000 ease-[cubic-bezier(0.32,0.72,0,1)] rtl:origin-[right]"
                                     x-data="{ shown: false }" x-init="requestAnimationFrame(() => shown = true)"
                                     :style="`transform: scaleX(${shown ? {{ $progress['percent'] / 100 }} : 0})`"></div>
                            </div>
                            @if ($progress['tier']?->benefits)
                                <p class="mt-2.5 text-xs text-white/80">{{ $progress['tier']->benefits }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            {{-- Wallet --}}
            <section class="mt-6" aria-labelledby="wallet-title">
                <h2 id="wallet-title" class="px-1 font-semibold text-ink-950">{{ __('guest.rewards.wallet') }}</h2>
                @if ($wallet->isEmpty())
                    <p class="mt-2 rounded-2xl bg-white px-4 py-5 text-center text-sm text-ink-500 ring-1 ring-ink-950/5">{{ __('guest.rewards.wallet_empty') }}</p>
                @else
                    <ul class="mt-2 space-y-2">
                        @foreach ($wallet as $item)
                            <li class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 ring-1 ring-ink-950/5" wire:key="wallet-{{ $item->id }}">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brass-50 text-brass-800 ring-1 ring-brass-500/15" aria-hidden="true">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 12v8H4v-8M2 7h20v5H2zM12 20V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Zm0 0h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-ink-950">{{ $item->reward->name }}</p>
                                    <p class="text-xs text-ink-500">{{ $describe($item->reward) }}@if ($item->expires_at) · {{ __('guest.savings.until', ['date' => $item->expires_at->setTimezone($tz)->format('d/m')]) }}@endif</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 px-1 text-xs text-ink-400">{{ __('guest.rewards.wallet_hint') }}</p>
                @endif
            </section>
        @endif

        {{-- Catalog --}}
        <section class="mt-8" aria-labelledby="catalog-title">
            <h2 id="catalog-title" class="px-1 font-semibold text-ink-950">{{ __('guest.rewards.catalog') }}</h2>
            @if ($catalog->isEmpty())
                <p class="mt-2 rounded-2xl bg-white px-4 py-5 text-center text-sm text-ink-500 ring-1 ring-ink-950/5">{{ __('guest.rewards.catalog_empty') }}</p>
            @else
                <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($catalog as $reward)
                        @php($affordable = $customer && $customer->points_balance >= $reward->points_cost && ($reward->stock === null || $reward->stock > 0))
                        <li class="{{ $card }}" wire:key="reward-{{ $reward->id }}">
                            <div class="{{ $core }} flex h-full flex-col">
                                <p class="font-semibold text-ink-950">{{ $reward->name }}</p>
                                <p class="mt-0.5 text-sm text-ink-500">{{ $reward->description ?: $describe($reward) }}</p>
                                <div class="mt-auto flex items-center justify-between gap-3 pt-4">
                                    <span class="text-sm font-semibold tabular-nums text-brass-800">{{ __('guest.rewards.cost', ['points' => number_format($reward->points_cost)]) }}</span>
                                    @if ($reward->stock === 0)
                                        <span class="text-sm text-ink-400">{{ __('guest.rewards.sold_out') }}</span>
                                    @else
                                        <button type="button" wire:click="claim({{ $reward->id }})" wire:loading.attr="disabled" wire:target="claim({{ $reward->id }})" @disabled(! $affordable)
                                                class="min-h-11 rounded-full bg-[var(--brand)] px-4 text-sm font-semibold text-white transition active:scale-95 disabled:bg-ink-200 disabled:text-ink-500">
                                            {{ __('guest.rewards.claim') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($customer)
            {{-- History --}}
            <section class="mt-8" aria-labelledby="history-title">
                <h2 id="history-title" class="px-1 font-semibold text-ink-950">{{ __('guest.rewards.history') }}</h2>
                @if ($history->isEmpty())
                    <p class="mt-2 rounded-2xl bg-white px-4 py-5 text-center text-sm text-ink-500 ring-1 ring-ink-950/5">{{ __('guest.rewards.history_empty') }}</p>
                @else
                    <ul class="mt-2 divide-y divide-ink-950/5 rounded-2xl bg-white ring-1 ring-ink-950/5">
                        @foreach ($history as $entry)
                            <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="ledger-{{ $entry->id }}">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink-900">{{ $entry->type === LedgerType::Redeem && $entry->order === null ? __('guest.rewards.used_for_reward') : $entry->type->getLabel() }}@if ($entry->order) · {{ $entry->order->displayNumber() }}@endif</p>
                                    <p class="text-xs text-ink-500">
                                        {{ $entry->created_at->setTimezone($tz)->format('d/m/Y') }}
                                        @if ($entry->points > 0 && $entry->remaining > 0 && $entry->expires_at)
                                            · {{ __('guest.rewards.expires', ['date' => $entry->expires_at->setTimezone($tz)->format('d/m/Y')]) }}
                                        @endif
                                    </p>
                                </div>
                                <span @class(['shrink-0 text-sm font-semibold tabular-nums', 'text-teal-800' => $entry->points > 0, 'text-ink-500' => $entry->points < 0]) dir="ltr">{{ $entry->points > 0 ? '+' : '' }}{{ number_format($entry->points) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($past->isNotEmpty())
                <details class="mt-6 rounded-2xl bg-white ring-1 ring-ink-950/5">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center px-4 text-sm font-medium text-ink-600 [&::-webkit-details-marker]:hidden">{{ __('guest.rewards.past') }}</summary>
                    <ul class="divide-y divide-ink-950/5 px-4 pb-2">
                        @foreach ($past as $item)
                            <li class="flex justify-between gap-3 py-2.5 text-sm"><span class="text-ink-800">{{ $item->reward->name }}</span><span class="text-ink-400">{{ __('guest.rewards.status.'.$item->status) }}</span></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        @endif
    </x-customer.shell>
</div>
