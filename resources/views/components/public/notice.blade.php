@props(['title', 'message', 'restaurant' => null, 'action' => null, 'actionLabel' => null])
<x-layouts.site :title="$title.' · '.($restaurant?->name ?? config('app.name'))" body-class="grain bg-ivory-50">
    <x-slot:head>
        <meta name="robots" content="noindex">
    </x-slot:head>
    <div aria-hidden="true" class="pointer-events-none fixed inset-0">
        <div class="absolute -top-40 end-[-10rem] size-[34rem] rounded-full bg-teal-900/10 blur-3xl"></div>
        <div class="absolute -bottom-48 start-[-12rem] size-[30rem] rounded-full bg-brass-400/16 blur-3xl"></div>
    </div>

    <main id="main" class="relative z-10 grid min-h-dvh place-items-center px-4 py-16">
        <div class="bezel reveal w-full max-w-md">
            <div class="bezel-core px-8 py-12 text-center sm:px-10">
                @if ($restaurant)
                    <x-public.restaurant-logo :restaurant="$restaurant" class="mx-auto size-20" />
                @else
                    <div class="flex justify-center text-ink-950"><x-brand.logo /></div>
                @endif
                <h1 class="display mt-8 text-3xl text-ink-950">{{ $title }}</h1>
                <p class="mx-auto mt-4 max-w-sm leading-relaxed text-ink-600">{{ $message }}</p>
                @if ($action)
                    <a href="{{ $action }}" class="btn btn-primary mt-10">
                        <span>{{ $actionLabel }}</span>
                        <span class="btn-icon" aria-hidden="true">
                            <svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    </main>
</x-layouts.site>
