@props(['restaurant', 'customer' => null, 'active' => null, 'menuUrl' => null, 'bare' => false])
@php
    use App\Support\Locales;
    $next = Locales::other();
    $tabs = [
        ['home', route('public.restaurant', $restaurant), __('guest.nav.home'), 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z'],
        ['menu', $menuUrl ?? route('public.restaurant', $restaurant).'#branches', __('guest.nav.menu'), 'M4 6h16M4 12h16M4 18h10'],
        ['orders', route('customer.orders', $restaurant), __('guest.nav.orders'), 'M8 7V5a4 4 0 1 1 8 0v2m-11 0h14l-1 13H6L5 7Z'],
        ['account', route('customer.account', $restaurant), __('guest.nav.account'), 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0'],
    ];
@endphp
<div class="relative mx-auto flex min-h-dvh w-full max-w-2xl flex-col">
    <div aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 -z-0 h-[28rem] bg-gradient-to-b from-[color-mix(in_oklab,var(--brand)_10%,transparent)] to-transparent"></div>

    <header class="sticky top-0 z-30 bg-ivory-50/80 px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] backdrop-blur-xl sm:px-6">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('public.restaurant', $restaurant) }}" wire:navigate class="flex min-w-0 items-center gap-2.5">
                <x-public.restaurant-logo :restaurant="$restaurant" class="size-9 !ring-2" />
                <span class="truncate text-[0.9375rem] font-semibold text-ink-950">{{ $restaurant->name }}</span>
            </a>
            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ route('locale.switch', $next) }}" lang="{{ $next }}" class="grid h-9 place-items-center rounded-full bg-white/80 px-3.5 text-xs font-semibold text-ink-700 ring-1 ring-ink-950/8 transition hover:bg-white">
                    {{ Locales::native($next) }}
                </a>
                <a href="{{ route('customer.account', $restaurant) }}" wire:navigate aria-label="{{ __('guest.nav.account') }}"
                   class="grid size-9 place-items-center rounded-full bg-white/80 text-xs font-semibold text-ink-700 ring-1 ring-ink-950/8 transition hover:bg-white">
                    @if ($customer?->name)
                        {{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}
                    @else
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0" stroke-linecap="round"/></svg>
                    @endif
                </a>
            </div>
        </div>
    </header>

    <main id="main" @class(['relative z-10 flex-1 px-4 sm:px-6', 'pb-32' => ! $bare, 'pb-10' => $bare])>
        {{ $slot }}
    </main>

    @unless ($bare)
        <nav aria-label="{{ __('guest.nav.label') }}"
             class="fixed inset-x-0 bottom-0 z-40 mx-auto max-w-2xl px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            {{ $above ?? '' }}
            <ul class="grid grid-cols-4 rounded-[1.75rem] bg-white/90 p-1.5 shadow-[0_18px_40px_-18px_rgb(10_15_28/0.35)] ring-1 ring-ink-950/8 backdrop-blur-xl">
                @foreach ($tabs as [$key, $url, $label, $icon])
                    <li>
                        <a href="{{ $url }}" wire:navigate @if ($active === $key) aria-current="page" @endif
                           @class(['flex flex-col items-center gap-0.5 rounded-[1.35rem] py-2 text-[0.6875rem] font-medium transition',
                                   'bg-[var(--brand)] text-white' => $active === $key,
                                   'text-ink-500 hover:text-ink-900' => $active !== $key])>
                            <svg class="size-[1.35rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="{{ $icon }}" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endunless

    {{-- Toasts: $dispatch('toast', { message, tone }) from anywhere --}}
    <div x-data="{ toasts: [], push(e) { const id = Date.now(); this.toasts.push({ id, ...e.detail }); setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 3200) } }"
         x-on:toast.window="push($event)" aria-live="polite"
         class="pointer-events-none fixed inset-x-0 top-[max(4.5rem,calc(env(safe-area-inset-top)+4rem))] z-50 mx-auto flex max-w-md flex-col items-center gap-2 px-4">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-y-3 opacity-0" x-transition:leave="transition duration-200" x-transition:leave-end="opacity-0"
                 class="pointer-events-auto flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-medium shadow-lg ring-1"
                 :class="toast.tone === 'error' ? 'bg-white text-ink-900 ring-status-delayed/30' : 'bg-ink-950 text-white ring-white/10'">
                <span x-show="toast.tone !== 'error'" class="size-1.5 rounded-full bg-status-ready"></span>
                <span x-show="toast.tone === 'error'" class="size-1.5 rounded-full bg-status-delayed"></span>
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
</div>
