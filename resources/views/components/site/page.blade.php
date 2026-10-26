@props(['title', 'description' => null, 'active' => null])
@php
    use App\Domain\Services\Enums\Service;
    use App\Support\Locales;
    $next = Locales::other();
    $onHome = $active === 'home';
    $anchor = fn (string $id): string => $onHome ? "#{$id}" : route('home')."#{$id}";
    $link = 'rounded-full px-3.5 py-2 transition hover:bg-white hover:text-ink-950';
@endphp
<x-layouts.site :title="$title" :description="$description" body-class="grain overflow-x-hidden bg-ivory-50 text-ink-900" themeable>
    <x-slot:head>{{ $head ?? '' }}</x-slot:head>

    {{-- Ambient light, fixed so scrolling never repaints it --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-0">
        <div class="absolute -top-48 end-[-12rem] size-[46rem] rounded-full bg-teal-900/14 blur-3xl"></div>
        <div class="absolute top-[40%] start-[-14rem] size-[40rem] rounded-full bg-brass-400/18 blur-3xl"></div>
    </div>

    {{-- Floating island navigation --}}
    <header class="sticky top-0 z-30 px-3 pt-3 sm:px-4 sm:pt-5"
            x-data="{
                menu: false,
                services: false,
                mode: 'system',
                init() { try { this.mode = localStorage.getItem('dtp.site.theme') || 'system' } catch (e) {} },
                cycle() { this.apply({ system: 'light', light: 'dark', dark: 'system' }[this.mode] ?? 'system') },
                apply(mode) {
                    const root = document.documentElement;
                    root.classList.add('theme-switching');
                    this.mode = mode;
                    try { mode === 'system' ? localStorage.removeItem('dtp.site.theme') : localStorage.setItem('dtp.site.theme', mode) } catch (e) {}
                    if (mode === 'system') delete root.dataset.theme; else root.dataset.theme = mode;
                    requestAnimationFrame(() => requestAnimationFrame(() => root.classList.remove('theme-switching')));
                },
            }"
            x-on:keydown.escape.window="menu = false; services = false">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-2 rounded-full bg-white/70 py-1.5 pe-1.5 ps-3 shadow-[var(--shadow-soft)] ring-1 ring-ink-950/5 backdrop-blur-xl sm:gap-3 sm:py-2 sm:pe-2" aria-label="{{ __('site.nav.label') }}">
            <a href="{{ route('home') }}" class="shrink-0 rounded-full text-ink-950" aria-label="{{ config('app.name') }}"><x-brand.logo /></a>

            <ul class="hidden items-center gap-1 text-sm font-medium text-ink-600 md:flex">
                <li class="relative" x-on:click.outside="services = false">
                    <button type="button" x-on:click="services = ! services" :aria-expanded="services" aria-haspopup="true"
                            @class([$link, 'inline-flex items-center gap-1.5', 'bg-white text-ink-950' => $active === 'service'])>
                        {{ __('site.nav.services') }}
                        <svg class="size-3.5 transition duration-300" :class="services && 'rotate-180'" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div x-show="services" x-cloak x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]" x-transition:enter-start="-translate-y-1 opacity-0" x-transition:leave="transition duration-150" x-transition:leave-end="opacity-0"
                         class="absolute start-1/2 top-12 w-80 -translate-x-1/2 rounded-[1.75rem] bg-white/95 p-2 shadow-[var(--shadow-float)] ring-1 ring-ink-950/5 backdrop-blur-xl rtl:translate-x-1/2">
                        @foreach (Service::cases() as $service)
                            <a href="{{ route('site.service', $service->slug()) }}" class="flex items-start gap-3 rounded-[1.35rem] p-3 transition hover:bg-ivory-100">
                                <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-teal-900 text-brass-200"><x-filament::icon :icon="$service->getIcon()" class="size-5" /></span>
                                <span>
                                    <span class="block font-semibold text-ink-950">{{ $service->getLabel() }}</span>
                                    <span class="mt-0.5 block text-xs font-normal leading-relaxed text-ink-500">{{ __('services.taglines.'.$service->value) }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </li>
                <li><a href="{{ $anchor('how') }}" class="{{ $link }}">{{ __('site.nav.how') }}</a></li>
                <li><a href="{{ route('site.pricing') }}" @class([$link, 'bg-white text-ink-950' => $active === 'pricing']) @if ($active === 'pricing') aria-current="page" @endif>{{ __('site.nav.pricing') }}</a></li>
                <li><a href="{{ $anchor('faq') }}" class="{{ $link }}">{{ __('site.nav.faq') }}</a></li>
            </ul>

            <div class="flex items-center gap-1 sm:gap-1.5">
                <button type="button" x-on:click="cycle()" class="grid size-10 place-items-center rounded-full text-ink-700 transition hover:bg-white hover:text-ink-950"
                        :aria-label="{{ \Illuminate\Support\Js::from(__('site.theme.label')) }} + ': ' + { system: {{ \Illuminate\Support\Js::from(__('site.theme.system')) }}, light: {{ \Illuminate\Support\Js::from(__('site.theme.light')) }}, dark: {{ \Illuminate\Support\Js::from(__('site.theme.dark')) }} }[mode]"
                        :title="{ system: {{ \Illuminate\Support\Js::from(__('site.theme.system')) }}, light: {{ \Illuminate\Support\Js::from(__('site.theme.light')) }}, dark: {{ \Illuminate\Support\Js::from(__('site.theme.dark')) }} }[mode]">
                    <svg x-show="mode === 'system'" class="size-[1.15rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16.5V20" stroke-linecap="round"/></svg>
                    <svg x-show="mode === 'light'" x-cloak class="size-[1.15rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.5 12h-2M21.5 12h-2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4" stroke-linecap="round"/></svg>
                    <svg x-show="mode === 'dark'" x-cloak class="size-[1.15rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z" stroke-linejoin="round"/></svg>
                </button>
                <a href="{{ route('locale.switch', $next) }}" lang="{{ $next }}" hreflang="{{ $next }}" class="hidden rounded-full px-3 py-2 text-sm font-medium text-ink-700 transition hover:bg-white hover:text-ink-950 sm:inline-flex">{{ Locales::native($next) }}</a>
                <a href="{{ url('/app') }}" class="hidden rounded-full px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-white hover:text-ink-950 lg:inline-flex">{{ __('site.nav.sign_in') }}</a>
                <a href="#request" class="site-cta inline-flex min-h-10 items-center rounded-full px-4 text-sm font-semibold shadow-[var(--shadow-lift)] transition active:scale-[0.98]">{{ __('site.nav.demo') }}</a>
                <button type="button" x-on:click="menu = ! menu" :aria-expanded="menu" aria-controls="site-menu" class="grid size-10 place-items-center rounded-full text-ink-800 transition hover:bg-white md:hidden">
                    <span class="sr-only">{{ __('site.nav.menu') }}</span>
                    <span class="relative block h-3 w-4" aria-hidden="true">
                        <span class="absolute inset-x-0 top-0 h-[1.5px] rounded-full bg-current transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]" :class="menu && 'translate-y-[5.25px] rotate-45'"></span>
                        <span class="absolute inset-x-0 bottom-0 h-[1.5px] rounded-full bg-current transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]" :class="menu && '-translate-y-[5.25px] -rotate-45'"></span>
                    </span>
                </button>
            </div>
        </nav>

        {{-- Mobile menu --}}
        <div id="site-menu" x-show="menu" x-cloak x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]" x-transition:enter-start="-translate-y-2 opacity-0" x-transition:leave="transition duration-150" x-transition:leave-end="opacity-0"
             class="mx-auto mt-2 max-w-6xl rounded-[2rem] bg-white/95 p-2 shadow-[var(--shadow-float)] ring-1 ring-ink-950/5 backdrop-blur-xl md:hidden">
            @foreach (Service::cases() as $service)
                <a href="{{ route('site.service', $service->slug()) }}" class="flex min-h-14 items-center gap-3 rounded-[1.5rem] px-3 text-ink-900 transition hover:bg-ivory-100">
                    <span class="grid size-9 place-items-center rounded-xl bg-teal-900 text-brass-200"><x-filament::icon :icon="$service->getIcon()" class="size-[1.15rem]" /></span>
                    <span class="font-semibold">{{ $service->getLabel() }}</span>
                </a>
            @endforeach
            <div class="my-1 h-px bg-ink-950/6"></div>
            <a href="{{ route('site.pricing') }}" class="flex min-h-12 items-center rounded-[1.5rem] px-4 font-medium text-ink-800 hover:bg-ivory-100">{{ __('site.nav.pricing') }}</a>
            <a href="{{ $anchor('how') }}" x-on:click="menu = false" class="flex min-h-12 items-center rounded-[1.5rem] px-4 font-medium text-ink-800 hover:bg-ivory-100">{{ __('site.nav.how') }}</a>
            <a href="{{ $anchor('faq') }}" x-on:click="menu = false" class="flex min-h-12 items-center rounded-[1.5rem] px-4 font-medium text-ink-800 hover:bg-ivory-100">{{ __('site.nav.faq') }}</a>
            <div class="my-1 h-px bg-ink-950/6"></div>
            <div class="grid grid-cols-2 gap-2 p-1">
                <a href="{{ route('locale.switch', $next) }}" lang="{{ $next }}" class="flex min-h-12 items-center justify-center rounded-full bg-ivory-100 font-medium text-ink-800">{{ Locales::native($next) }}</a>
                <a href="{{ url('/app') }}" class="flex min-h-12 items-center justify-center rounded-full bg-ivory-100 font-medium text-ink-800">{{ __('site.nav.sign_in') }}</a>
            </div>
        </div>
    </header>

    <main id="main" class="relative z-10">
        {{ $slot }}
    </main>

    <footer class="relative z-10 px-4 pb-10 sm:px-6">
        <div class="mx-auto max-w-6xl border-t border-ink-950/8 pt-10">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <x-brand.logo class="text-ink-950" />
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-500">{{ __('app.tagline') }}</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-ink-950">{{ __('site.footer.product') }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-ink-600">
                        @foreach (Service::cases() as $service)
                            <li><a href="{{ route('site.service', $service->slug()) }}" class="transition hover:text-ink-950">{{ $service->getLabel() }}</a></li>
                        @endforeach
                        <li><a href="{{ route('site.pricing') }}" class="transition hover:text-ink-950">{{ __('site.nav.pricing') }}</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-sm font-semibold text-ink-950">{{ __('site.footer.company') }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-ink-600">
                        <li><a href="{{ url('/app') }}" class="transition hover:text-ink-950">{{ __('site.footer.restaurant_login') }}</a></li>
                        <li><a href="{{ url('/admin') }}" class="transition hover:text-ink-950">{{ __('site.footer.platform') }}</a></li>
                        <li><a href="{{ route('api.docs') }}" class="transition hover:text-ink-950">{{ __('site.footer.api') }}</a></li>
                    </ul>
                </div>
            </div>
            <p class="mt-12 text-sm text-ink-500">© <span dir="ltr">{{ now()->year }}</span> {{ config('app.name') }}. {{ __('app.home.rights') }}</p>
        </div>
    </footer>

    <style>
        @keyframes site-ring { from { transform: scale(0.8); opacity: 0.9; } to { transform: scale(1.45); opacity: 0; } }
        @keyframes site-float { 0%, 100% { translate: 0 0; } 50% { translate: 0 -10px; } }
        @keyframes site-glow { 0%, 100% { box-shadow: 0 0 0 0 color-mix(in oklab, var(--color-status-ready) 0%, transparent); } 50% { box-shadow: 0 0 0 8px color-mix(in oklab, var(--color-status-ready) 25%, transparent); } }
    </style>
</x-layouts.site>
