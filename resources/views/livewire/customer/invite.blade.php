<div>
    <style>@keyframes invite-rise { from { transform: translateY(12px) scale(0.96); opacity: 0; } to { transform: none; opacity: 1; } }</style>
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" bare>
        <section class="mx-auto max-w-md pt-8" wire:key="invite-{{ $state }}">
            <div class="rounded-[calc(2rem*var(--radius-scale))] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5">
                <div class="relative overflow-hidden rounded-[max(0px,calc(2rem*var(--radius-scale)-0.375rem))] bg-gradient-to-br from-ivory-100 to-white px-5 pb-6 pt-7">
                    <div aria-hidden="true" class="pointer-events-none absolute -end-20 -top-24 size-64 rounded-full bg-[color-mix(in_oklab,var(--brand)_14%,transparent)] blur-3xl"></div>

                    <div class="relative">
                        @if ($state === 'joined')
                            <span class="grid size-14 place-items-center rounded-full bg-[var(--brand)] text-on-brand motion-safe:animate-[invite-rise_520ms_cubic-bezier(0.32,0.72,0,1)_both]">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        @else
                            <x-public.restaurant-logo :restaurant="$restaurant" class="size-14" />
                        @endif

                        @if ($state === 'welcome')
                            <p class="mt-5 inline-flex rounded-full bg-[color-mix(in_oklab,var(--brand)_10%,white)] px-3 py-1 text-[0.6875rem] font-semibold uppercase tracking-[0.18em] text-[var(--brand)] ring-1 ring-[var(--brand)]/15 rtl:tracking-normal">{{ __('referrals.invite.eyebrow') }}</p>
                            <h1 class="display mt-3 text-3xl text-ink-950">
                                {{ $referrer?->firstName()
                                    ? __('referrals.invite.title', ['name' => $referrer->firstName(), 'restaurant' => $restaurant->name])
                                    : __('referrals.invite.title_anonymous', ['restaurant' => $restaurant->name]) }}
                            </h1>
                        @else
                            <h1 class="display mt-5 text-3xl text-ink-950">{{ __('referrals.invite.'.$state.'_title') }}</h1>
                            <p class="mt-1.5 text-ink-500">{{ __('referrals.invite.'.$state.'_body', ['restaurant' => $restaurant->name]) }}</p>
                        @endif

                        {{-- The welcome gift --}}
                        @if ($gift && in_array($state, ['welcome', 'joined'], true))
                            <div class="mt-6 flex items-center gap-3.5 rounded-2xl bg-white p-3.5 ring-1 ring-brass-500/20 shadow-[var(--shadow-hairline)] motion-safe:animate-[invite-rise_600ms_120ms_cubic-bezier(0.32,0.72,0,1)_both]">
                                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-brass-50 text-brass-800 ring-1 ring-brass-500/15" aria-hidden="true">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 12v8H4v-8M2 7h20v5H2zM12 20V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Zm0 0h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-ink-500">{{ __('referrals.invite.gift') }}</p>
                                    <p class="truncate font-semibold text-ink-950">{{ $gift->name }}</p>
                                    <p class="text-xs text-ink-500">{{ $gift->description ?: $gift->gives($restaurant->currency) }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($state === 'welcome')
                            <ol class="mt-5 grid grid-cols-3 gap-2 text-center">
                                @foreach (['sign_in', 'order', 'gift'] as $step)
                                    <li class="rounded-2xl bg-white/70 px-2 py-3 ring-1 ring-ink-950/5">
                                        <span class="mx-auto grid size-7 place-items-center rounded-full bg-ink-950 text-xs font-semibold text-white tabular-nums">{{ $loop->iteration }}</span>
                                        <span class="mt-2 block text-xs font-medium leading-snug text-ink-700">{{ __('referrals.invite.how.'.$step) }}</span>
                                    </li>
                                @endforeach
                            </ol>

                            <div class="mt-6">@include('livewire.customer.partials.phone-sign-in')</div>
                        @endif

                        @if ($state === 'joined')
                            <a href="{{ route('public.restaurant', $restaurant) }}#branches" wire:navigate
                               class="group mt-6 flex w-full items-center justify-center gap-3 rounded-full bg-[var(--brand)] py-2 pe-2 ps-6 text-[0.9375rem] font-semibold text-on-brand transition active:scale-[0.98]">
                                <span class="flex-1 text-center">{{ __('referrals.invite.order_now') }}</span>
                                <span class="grid size-10 place-items-center rounded-full bg-white/15 transition group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" aria-hidden="true">
                                    <svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                            </a>
                        @elseif (in_array($state, ['own', 'member'], true))
                            <a href="{{ route('customer.rewards', $restaurant) }}" wire:navigate
                               class="mt-6 block rounded-full bg-ink-950 px-6 py-3.5 text-center text-[0.9375rem] font-semibold text-white transition active:scale-[0.98]">{{ __('referrals.invite.my_rewards') }}</a>
                        @elseif ($state === 'invalid')
                            <a href="{{ route('public.restaurant', $restaurant) }}" wire:navigate
                               class="mt-6 block rounded-full bg-ink-950 px-6 py-3.5 text-center text-[0.9375rem] font-semibold text-white transition active:scale-[0.98]">{{ __('referrals.invite.see_menu') }}</a>
                        @endif
                    </div>
                </div>
            </div>

            @if (in_array($state, ['own', 'member'], true))
                <a href="{{ route('public.restaurant', $restaurant) }}" wire:navigate class="mt-6 block text-center text-sm font-medium text-ink-500">{{ __('referrals.invite.see_menu') }}</a>
            @endif
        </section>
    </x-customer.shell>
</div>
