@php
    use App\Domain\Loyalty\Enums\LedgerType;
    use App\Domain\Loyalty\Enums\RewardType;
    use App\Support\Money;
    $card = 'rounded-[calc(1.75rem*var(--radius-scale))] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5';
    $core = 'rounded-[max(0px,calc(1.75rem*var(--radius-scale)-0.375rem))] p-4';
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
            @if ($program)
                <p class="mt-1 text-sm text-ink-500">{{ __('guest.rewards.subtitle', ['points' => number_format((int) $program->earn_points_per_unit), 'currency' => Money::symbol($currency)]) }}</p>
            @elseif ($stampPrograms->isNotEmpty())
                <p class="mt-1 text-sm text-ink-500">{{ __('guest.rewards.stamps_subtitle') }}</p>
            @else
                <p class="mt-1 text-sm text-ink-500">{{ __('referrals.card.invite_friends') }}</p>
            @endif
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
            @if ($program)
            <section class="mt-6 rounded-[calc(2rem*var(--radius-scale))] bg-[color-mix(in_oklab,var(--brand)_12%,white)] p-1.5 ring-1 ring-[var(--brand)]/15" aria-labelledby="balance-title">
                <div class="relative overflow-hidden rounded-[max(0px,calc(2rem*var(--radius-scale)-0.375rem))] bg-[var(--brand)] p-5 text-on-brand shadow-[inset_0_1px_0_rgb(255_255_255/0.18)]">
                    <div aria-hidden="true" class="pointer-events-none absolute -end-16 -top-20 size-56 rounded-full bg-white/10 blur-2xl"></div>
                    <p id="balance-title" class="text-xs font-medium uppercase tracking-[0.18em] text-on-brand/70 rtl:tracking-normal">{{ __('guest.rewards.balance') }}</p>
                    <p class="mt-1 text-5xl font-semibold tabular-nums tracking-tight" dir="ltr">{{ number_format($customer->points_balance) }}</p>
                    <p class="mt-1 text-sm text-on-brand/75" dir="ltr">≈ {{ Money::format($program->valueOf($customer->points_balance), $currency) }}</p>

                    @if ($progress && $tiers->isNotEmpty())
                        <div class="mt-5 rounded-2xl bg-white/10 p-3.5 ring-1 ring-white/15">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-semibold">{{ $progress['tier']?->name ?? __('guest.rewards.no_tier') }}</span>
                                @if ($progress['next'])
                                    <span class="text-on-brand/75">{{ __('guest.rewards.to_next', ['points' => number_format($progress['needed']), 'tier' => $progress['next']->name]) }}</span>
                                @else
                                    <span class="text-on-brand/75">{{ __('guest.rewards.top_tier') }}</span>
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
            @endif

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

        {{-- Happy birthday: the gift that arrived this week --}}
        @if ($birthdayGift && $birthdayGift->walletReward)
            <section class="mt-6 overflow-hidden rounded-[calc(1.75rem*var(--radius-scale))] bg-brass-50 p-1.5 ring-1 ring-brass-500/20" aria-labelledby="birthday-title" wire:key="birthday-{{ $birthdayGift->id }}">
                <div class="{{ $core }} relative bg-white">
                    <div aria-hidden="true" class="pointer-events-none absolute -end-10 -top-12 size-40 rounded-full bg-brass-100/70 blur-2xl"></div>
                    <div class="relative flex items-start gap-3.5">
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-brass-500 text-on-accent shadow-[inset_0_1px_0_rgb(255_255_255/0.25)]" aria-hidden="true">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 21h16M5 21v-7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7M5 16c1.2 1 2.4 1 3.5 0s2.3-1 3.5 0 2.3 1 3.5 0 2.3-1 3.5 0M12 12V8m0 0c-1 0-1.6-.8-1.6-1.6S12 4 12 4s1.6 1.6 1.6 2.4S13 8 12 8Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="birthday-title" class="display text-xl text-ink-950">{{ __('loyalty.birthday.card_title', ['name' => $customer?->firstName() ?? '']) }}</h2>
                            <p class="mt-1 text-sm text-ink-600">{{ __('loyalty.birthday.card_body', ['reward' => $birthdayGift->walletReward->reward->name]) }}</p>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- Ask for the birthday, when a gift is waiting for it --}}
        @if ($askBirthday)
            <section class="mt-6 {{ $card }}" aria-labelledby="ask-birthday-title">
                <form wire:submit="saveBirthday" class="{{ $core }}">
                    <h2 id="ask-birthday-title" class="font-semibold text-ink-950">{{ __('loyalty.birthday.ask_title') }}</h2>
                    <p class="mt-0.5 text-sm text-ink-500">{{ __('loyalty.birthday.ask_body', ['restaurant' => $restaurant->name]) }}</p>
                    <div class="mt-3 flex items-end gap-2">
                        <label class="min-w-0 flex-1">
                            <span class="sr-only">{{ __('loyalty.birthday.ask_label') }}</span>
                            <input wire:model="birthday" type="date" max="{{ now()->subDay()->format('Y-m-d') }}" min="1900-01-02" dir="ltr" required
                                   class="min-h-12 w-full rounded-2xl border-0 bg-ivory-100 px-4 text-[1rem] text-ink-950 ring-1 ring-ink-950/5 focus:ring-2 focus:ring-[var(--brand)]">
                        </label>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveBirthday"
                                class="min-h-12 shrink-0 rounded-full bg-[var(--brand)] px-5 font-semibold text-on-brand transition active:scale-95 disabled:opacity-60">{{ __('loyalty.birthday.ask_save') }}</button>
                    </div>
                    @error('birthday')<p class="mt-1.5 text-sm text-status-delayed" role="alert">{{ $message }}</p>@enderror
                </form>
            </section>
        @endif

        {{-- Achievements: badges earned and the weekly streak --}}
        @if ($achievements)
            <section class="mt-8" aria-labelledby="achievements-title">
                <h2 id="achievements-title" class="px-1 font-semibold text-ink-950">{{ __('loyalty.badges.achievements') }}</h2>
                @if ($achievements['streak'])
                    <p class="mt-1 flex items-center gap-1.5 px-1 text-sm text-ink-600">
                        <svg class="size-4 shrink-0 text-brass-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="{{ \App\Domain\Loyalty\Badges\Badge::Streak->icon() }}" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ trans_choice('loyalty.badges.streak_line', $achievements['streak']['weeks'], ['count' => $achievements['streak']['weeks'], 'date' => $achievements['streak']['keepBy']->setTimezone($tz)->translatedFormat('l j M')]) }}
                    </p>
                @endif
                <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($achievements['badges'] as $badge)
                        @php
                            $row = $achievements['progress'][$badge->value];
                            $earned = $row['earned'] !== null;
                            $share = $row['target'] > 0 ? min(100, (int) round($row['current'] / $row['target'] * 100)) : 0;
                        @endphp
                        <li @class(['rounded-2xl p-3.5 ring-1', 'bg-white ring-brass-500/25' => $earned, 'bg-white/60 ring-ink-950/5' => ! $earned]) wire:key="badge-{{ $badge->value }}">
                            <span @class(['grid size-11 place-items-center rounded-full', 'bg-brass-500 text-on-accent shadow-[inset_0_1px_0_rgb(255_255_255/0.25)]' => $earned, 'bg-ink-950/5 text-ink-300' => ! $earned]) aria-hidden="true">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="{{ $badge->icon() }}" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <p @class(['mt-2.5 text-sm font-semibold', 'text-ink-950' => $earned, 'text-ink-600' => ! $earned])>{{ $badge->label() }}</p>
                            <p class="mt-0.5 text-xs leading-snug text-ink-500">{{ $badge->description($achievements['progress']['streak']['target']) }}</p>
                            @if ($earned)
                                <p class="mt-2 text-xs font-medium text-brass-800">{{ __('loyalty.badges.earned_on', ['date' => $row['earned']->setTimezone($tz)->format('d/m/Y')]) }}</p>
                            @else
                                <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-ink-950/8" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $row['target'] }}" aria-valuenow="{{ min($row['current'], $row['target']) }}" aria-label="{{ $badge->label() }}">
                                    <div class="h-full rounded-full bg-[var(--brand)]" style="inline-size: {{ $share }}%"></div>
                                </div>
                                <p class="mt-1 text-[0.6875rem] tabular-nums text-ink-400">{{ __('loyalty.badges.progress', ['current' => min($row['current'], $row['target']), 'target' => $row['target']]) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- A badge the guest hasn't seen yet: celebrated once, then marked seen --}}
            @if ($achievements['fresh']->isNotEmpty())
                @php
                    $newest = $achievements['fresh']->sortByDesc('earned_at')->first();
                @endphp
                @teleport('body')
                <div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-ink-950/40 px-6 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="unlocked-title"
                     x-on:keydown.escape.window="open = false" wire:key="unlocked-{{ $newest->id }}">
                    <div class="w-full max-w-sm rounded-[calc(2rem*var(--radius-scale))] bg-white p-6 text-center shadow-2xl" x-on:click.outside="open = false"
                         x-init="matchMedia('(prefers-reduced-motion: reduce)').matches || $el.animate([{ transform: 'scale(.9)', opacity: 0 }, { transform: 'scale(1)', opacity: 1 }], { duration: 420, easing: 'cubic-bezier(.32,.72,0,1)' })">
                        <span class="mx-auto grid size-20 place-items-center rounded-full bg-brass-500 text-on-accent shadow-[0_18px_40px_-18px_var(--color-brass-700,currentColor)]" aria-hidden="true">
                            <svg class="size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="{{ $newest->badge->icon() }}" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <p id="unlocked-title" class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-brass-700 rtl:tracking-normal">{{ __('loyalty.badges.unlocked') }}</p>
                        <p class="display mt-1 text-2xl text-ink-950">{{ $newest->badge->label() }}</p>
                        @if ($newest->walletReward)
                            <p class="mt-2 text-sm text-ink-600">{{ __('loyalty.badges.unlocked_reward', ['reward' => $newest->walletReward->reward->name]) }}</p>
                        @endif
                        <button type="button" x-on:click="open = false" class="mt-6 w-full rounded-full bg-ink-950 py-3.5 font-semibold text-white transition active:scale-[0.98]">{{ __('guest.close') }}</button>
                    </div>
                </div>
                @endteleport
            @endif
        @endif

        {{-- Invite friends --}}
        @if ($invite)
            <section class="mt-8 rounded-[calc(1.75rem*var(--radius-scale))] bg-[color-mix(in_oklab,var(--brand)_8%,white)] p-1.5 ring-1 ring-[var(--brand)]/15" aria-labelledby="invite-title"
                     x-data="{
                        copied: false,
                        url: @js($invite['url']),
                        copy() { navigator.clipboard?.writeText(this.url); this.copied = true; setTimeout(() => this.copied = false, 1800) },
                        share() {
                            if (navigator.share) { navigator.share({ title: @js($restaurant->name), text: @js(__('referrals.card.share_text', ['restaurant' => $restaurant->name, 'theirs' => $invite['friend']->name])), url: this.url }).catch(() => {}) }
                            else { this.copy() }
                        },
                     }">
                <div class="{{ $core }} bg-white">
                    <div class="flex items-start gap-3.5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-[var(--brand)] text-on-brand shadow-[inset_0_1px_0_rgb(255_255_255/0.2)]" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 19a4 4 0 0 0-8 0M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19 8v6M22 11h-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 id="invite-title" class="font-semibold text-ink-950">{{ __('referrals.card.title') }}</h2>
                            <p class="mt-0.5 text-sm text-ink-500">{{ __('referrals.card.body', ['mine' => $invite['referrer']->name, 'theirs' => $invite['friend']->name]) }}</p>
                        </div>
                    </div>

                    @if ($invite['url'])
                        <div class="mt-4 flex items-center gap-2 rounded-2xl bg-ivory-100 p-1.5 ps-4 ring-1 ring-ink-950/5">
                            <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink-700" dir="ltr">{{ preg_replace('#^https?://#', '', $invite['url']) }}</span>
                            <button type="button" x-on:click="copy()" class="min-h-10 shrink-0 rounded-full bg-white px-4 text-sm font-semibold text-ink-800 ring-1 ring-ink-950/10 transition active:scale-95">
                                <span x-show="! copied">{{ __('referrals.card.copy') }}</span>
                                <span x-show="copied" x-cloak>{{ __('referrals.card.copied') }}</span>
                            </button>
                        </div>
                        <button type="button" x-on:click="share()"
                                class="mt-3 flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-[var(--brand)] px-6 text-[0.9375rem] font-semibold text-on-brand transition active:scale-[0.98]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 15V3m0 0L8 7m4-4 4 4M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ __('referrals.card.share') }}
                        </button>
                        <p class="mt-3 text-center text-xs text-ink-500">
                            {{ trans_choice('referrals.card.joined', $invite['stats']['joined'], ['count' => $invite['stats']['joined']]) }}{{ trans_choice('referrals.card.rewarded', $invite['stats']['rewarded'], ['count' => $invite['stats']['rewarded']]) }}
                        </p>
                    @else
                        <p class="mt-3 text-sm font-medium text-[var(--brand)]">{{ __('referrals.card.sign_in') }}</p>
                    @endif
                </div>
            </section>
        @endif

        {{-- Stamp cards --}}
        @if ($stampPrograms->isNotEmpty())
            <section class="mt-8" aria-labelledby="stamps-title">
                <h2 id="stamps-title" class="px-1 font-semibold text-ink-950">{{ __('guest.rewards.stamp_cards') }}</h2>

                {{-- At the counter: ask the waiter for today's stamp --}}
                @if ($counterBranch && $customer)
                    <div class="mt-3 rounded-[calc(1.75rem*var(--radius-scale))] bg-[color-mix(in_oklab,var(--brand)_10%,white)] p-1.5 ring-1 ring-[var(--brand)]/15"
                         x-data x-on:stamp-added.window="navigator.vibrate?.([60, 40, 120])">
                        @if ($stampRequest?->isWaiting())
                            <div wire:poll.3s="checkStamp" class="flex items-start gap-4 rounded-[max(0px,calc(1.75rem*var(--radius-scale)-0.375rem))] bg-white p-4" role="status" aria-live="polite">
                                <span class="relative grid size-12 shrink-0 place-items-center" aria-hidden="true">
                                    <span class="absolute inset-0 rounded-full bg-[var(--brand)]/20 motion-safe:animate-ping"></span>
                                    <span class="relative grid size-12 place-items-center rounded-full bg-[var(--brand)] text-on-brand">
                                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-ink-950">{{ __('guest.rewards.counter.waiting') }}</p>
                                    <p class="text-sm text-ink-500">{{ __('guest.rewards.counter.waiting_body', ['branch' => $counterBranch->name]) }}</p>
                                    <button type="button" wire:click="cancelStamp" class="-ms-1 mt-1 min-h-11 rounded-full px-1 text-sm font-medium text-ink-500 underline-offset-4 hover:text-ink-900 hover:underline">{{ __('guest.rewards.counter.cancel') }}</button>
                                </div>
                            </div>
                        @else
                            <div class="rounded-[max(0px,calc(1.75rem*var(--radius-scale)-0.375rem))] bg-white p-4">
                                <button type="button" wire:click="askStamp" wire:loading.attr="disabled" wire:target="askStamp"
                                        class="group flex min-h-14 w-full items-center justify-between gap-3 rounded-full bg-[var(--brand)] ps-6 pe-2 text-base font-semibold text-on-brand transition active:scale-[0.98] disabled:opacity-70">
                                    <span>{{ __('guest.rewards.counter.ask') }}</span>
                                    <span class="grid size-10 place-items-center rounded-full bg-white/15 transition group-hover:scale-105" aria-hidden="true">
                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                                    </span>
                                </button>
                                <p class="mt-2.5 px-2 text-center text-xs text-ink-500">{{ __('guest.rewards.counter.ask_hint', ['branch' => $counterBranch->name]) }}</p>
                            </div>
                        @endif
                    </div>
                @elseif ($counterBranch)
                    <p class="mt-2 rounded-2xl bg-white px-4 py-3 text-sm text-ink-600 ring-1 ring-ink-950/5">{{ __('guest.rewards.counter.sign_in') }}</p>
                @elseif ($customer)
                    <p class="mt-1 px-1 text-xs text-ink-500">{{ __('guest.rewards.counter.scan_hint') }}</p>
                @endif
                <ul class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach ($stampPrograms as $stampProgram)
                        @php
                            $filled = $stampCards->get($stampProgram->id)?->stamps ?? 0;
                            $left = $stampProgram->stamps_needed - $filled;
                        @endphp
                        <li wire:key="stamp-{{ $stampProgram->id }}">
                            <x-stamp-card :name="$stampProgram->name" :needed="$stampProgram->stamps_needed" :filled="$filled"
                                          :target="$stampProgram->targetName()" :reward="$stampProgram->reward->name" animate />
                            <p class="mt-2 px-1 text-xs text-ink-500">
                                {{ $customer ? trans_choice('guest.rewards.stamps_left', $left, ['count' => $left]) : __('guest.rewards.stamps_sign_in') }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Catalog --}}
        @if ($program)
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
                                                class="min-h-11 rounded-full bg-[var(--brand)] px-4 text-sm font-semibold text-on-brand transition active:scale-95 disabled:bg-ink-200 disabled:text-ink-500">
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
        @endif

        @if ($customer && $program)
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
                                    <p class="text-sm font-medium text-ink-900">{{ match (true) {
                                        $entry->order === null && $entry->type === LedgerType::Redeem => __('guest.rewards.used_for_reward'),
                                        $entry->order === null && $entry->type === LedgerType::Earn => __('guest.rewards.points_added'),
                                        default => $entry->type->getLabel(),
                                    } }}@if ($entry->order) · {{ $entry->order->displayNumber() }}@endif</p>
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
        @endif

        @if ($customer)
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
