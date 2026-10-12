@php
    use App\Support\Locales;
    $next = Locales::other();
@endphp
<x-layouts.site :title="__('app.home.title')" :description="__('app.home.description')" body-class="grain overflow-x-hidden">
    {{-- Ambient light: two soft orbs, fixed so scrolling never repaints them. --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-0">
        <div class="absolute -top-40 end-[-10rem] size-[42rem] rounded-full bg-teal-900/15 blur-3xl"></div>
        <div class="absolute -bottom-48 start-[-12rem] size-[38rem] rounded-full bg-brass-400/20 blur-3xl"></div>
    </div>

    {{-- Floating island navigation --}}
    <header class="relative z-20 px-4 pt-5 sm:pt-6">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-3 rounded-full bg-white/60 py-2 ps-3 pe-2 shadow-[var(--shadow-soft)] ring-1 ring-ink-950/5 backdrop-blur-xl" aria-label="Primary">
            <a href="{{ route('home') }}" class="rounded-full text-ink-950" aria-label="{{ config('app.name') }}">
                <x-brand.logo />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-2">
                <a href="{{ route('locale.switch', $next) }}" lang="{{ $next }}" hreflang="{{ $next }}"
                   class="rounded-full px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-white hover:text-ink-950">
                    {{ Locales::native($next) }}
                </a>
                <a href="{{ url('/app') }}"
                   class="hidden rounded-full bg-ink-950 px-4 py-2 text-sm font-medium text-white shadow-[var(--shadow-lift)] transition hover:bg-ink-800 sm:inline-flex">
                    {{ __('app.home.restaurant_login') }}
                </a>
            </div>
        </nav>
    </header>

    <main id="main" class="relative z-10">
        <section class="mx-auto grid min-h-[calc(100dvh-6rem)] max-w-6xl items-center gap-14 px-4 py-16 sm:px-6 lg:grid-cols-12 lg:gap-10 lg:py-24">
            {{-- Editorial copy --}}
            <div class="lg:col-span-7">
                <p class="eyebrow reveal" style="--i:0">
                    <span class="size-1.5 rounded-full bg-brass-500"></span>
                    {{ __('app.home.eyebrow') }}
                </p>

                <h1 class="display reveal mt-7 text-[2.75rem] leading-[1.02] text-ink-950 sm:text-6xl lg:text-[4.75rem] rtl:text-[2.5rem] rtl:leading-[1.35] rtl:sm:text-[3.25rem] rtl:lg:text-[4rem]" style="--i:1">
                    {{ __('app.home.headline') }}
                </h1>

                <p class="reveal mt-7 max-w-xl text-lg leading-relaxed text-ink-600" style="--i:2">
                    {{ __('app.home.lead') }}
                </p>

                <div class="reveal mt-10 flex flex-wrap items-center gap-3" style="--i:3">
                    <a href="{{ url('/app') }}" class="btn btn-primary group">
                        <span>{{ __('app.home.restaurant_login') }}</span>
                        <span class="btn-icon" aria-hidden="true">
                            <svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </a>
                    <a href="{{ url('/admin') }}" class="btn btn-ghost group">
                        <span>{{ __('app.home.platform_login') }}</span>
                        <span class="btn-icon" aria-hidden="true">
                            <svg class="size-4" viewBox="0 0 16 16" fill="none"><path d="M8 1.75 2.75 4v3.6c0 3.1 2.2 5.6 5.25 6.65 3.05-1.05 5.25-3.55 5.25-6.65V4L8 1.75Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                        </span>
                    </a>
                </div>

                <ul class="reveal mt-12 flex flex-wrap gap-x-7 gap-y-3 text-sm text-ink-600" style="--i:4">
                    @foreach (['scan', 'kitchen', 'arrival'] as $point)
                        <li class="flex items-center gap-2.5">
                            <span class="grid size-5 place-items-center rounded-full bg-teal-900/8 text-teal-800 ring-1 ring-teal-900/10">
                                <svg class="size-3" viewBox="0 0 12 12" fill="none"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            {{ __("app.home.points.{$point}") }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Product moment: a live order ticket in a double-bezel tray --}}
            <div class="reveal lg:col-span-5" style="--i:2" aria-hidden="true">
                <div class="bezel mx-auto max-w-sm rotate-0 lg:rotate-[1.5deg] rtl:lg:rotate-[-1.5deg]">
                    <div class="bezel-core overflow-hidden">
                        <div class="flex items-center justify-between bg-teal-900 px-6 py-5 text-white">
                            <div>
                                <p class="text-[0.6875rem] font-medium uppercase tracking-[0.18em] text-teal-200 rtl:tracking-normal">{{ __('app.home.points.kitchen') }}</p>
                                <p class="mt-1 font-display text-3xl tracking-tight" dir="ltr">A582</p>
                            </div>
                            <span class="relative flex size-3">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-status-ready/60 motion-reduce:hidden"></span>
                                <span class="relative inline-flex size-3 rounded-full bg-status-ready"></span>
                            </span>
                        </div>
                        <div class="space-y-4 px-6 py-6">
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 place-items-center rounded-2xl bg-ivory-100 text-lg">🍔</span>
                                <div class="h-2.5 flex-1 rounded-full bg-ink-100"></div>
                                <span class="text-sm font-medium tabular-nums text-ink-700" dir="ltr">2×</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 place-items-center rounded-2xl bg-ivory-100 text-lg">🥤</span>
                                <div class="h-2.5 w-2/3 rounded-full bg-ink-100"></div>
                                <span class="ms-auto text-sm font-medium tabular-nums text-ink-700" dir="ltr">1×</span>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl bg-ivory-100 px-4 py-3">
                                <span class="flex items-center gap-2 text-sm font-medium text-ink-800">
                                    <span>🚗</span> {{ __('app.home.points.arrival') }}
                                </span>
                                <span class="rounded-full bg-status-ready/12 px-2.5 py-1 text-xs font-semibold text-green-800" dir="ltr">● 3 min</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @include('partials.request-access')
    </main>

    <footer class="relative z-10 px-4 pb-8 sm:px-6">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 border-t border-ink-950/5 pt-6 text-sm text-ink-500 sm:flex-row">
            <p>© <span dir="ltr">{{ now()->year }}</span> {{ config('app.name') }}. {{ __('app.home.rights') }}</p>
            <p>{{ __('app.tagline') }}</p>
        </div>
    </footer>
</x-layouts.site>
