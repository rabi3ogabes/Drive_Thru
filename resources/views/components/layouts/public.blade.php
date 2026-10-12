@props(['restaurant', 'title' => null, 'description' => null])
@php
    use App\Support\Locales;
    $next = Locales::other();
@endphp
<x-layouts.site :title="($title ? $title.' · ' : '').$restaurant->name" :description="$description ?? __('public.meta.description', ['restaurant' => $restaurant->name])" body-class="grain bg-ivory-50 text-ink-900">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0">
        <div class="absolute -top-40 end-[-10rem] size-[34rem] rounded-full bg-teal-900/8 blur-3xl"></div>
        <div class="absolute -bottom-48 start-[-12rem] size-[30rem] rounded-full bg-brass-400/14 blur-3xl"></div>
    </div>

    <div class="relative z-10 mx-auto flex min-h-dvh w-full max-w-3xl flex-col px-4 pb-10 pt-5 sm:px-6 sm:pt-8">
        <header class="flex items-center justify-between gap-4">
            <a href="{{ route('public.restaurant', $restaurant) }}" class="flex min-w-0 items-center gap-3 rounded-full pe-4 transition hover:opacity-80">
                <x-public.restaurant-logo :restaurant="$restaurant" class="size-10" />
                <span class="truncate font-semibold text-ink-950">{{ $restaurant->name }}</span>
            </a>
            <a href="{{ route('locale.switch', $next) }}" lang="{{ $next }}" class="shrink-0 rounded-full bg-white/70 px-4 py-2 text-sm font-medium text-ink-700 shadow-[var(--shadow-hairline)] backdrop-blur transition hover:bg-white">
                {{ Locales::native($next) }}
            </a>
        </header>

        <main id="main" class="mt-8 flex-1">
            {{ $slot }}
        </main>

        <footer class="mt-16 flex items-center justify-center gap-2 text-xs text-ink-400">
            <span>{{ __('public.powered_by') }}</span>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 font-medium text-ink-600 transition hover:text-ink-900">
                <x-brand.mark class="size-4" />
                {{ config('app.name') }}
            </a>
        </footer>
    </div>
</x-layouts.site>
