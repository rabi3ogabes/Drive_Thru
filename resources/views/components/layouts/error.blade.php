@props(['code', 'icon' => 'compass'])
@php
    $title = __("errors.{$code}.title");
    $message = __("errors.{$code}.message");
@endphp
<x-layouts.site :title="$title.' · '.config('app.name')" body-class="grain">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0">
        <div class="absolute -top-40 end-[-10rem] size-[38rem] rounded-full bg-teal-900/12 blur-3xl"></div>
        <div class="absolute -bottom-48 start-[-12rem] size-[34rem] rounded-full bg-brass-400/18 blur-3xl"></div>
    </div>

    <main id="main" class="relative z-10 grid min-h-dvh place-items-center px-4 py-16">
        <div class="bezel reveal w-full max-w-lg">
            <div class="bezel-core px-8 py-12 text-center sm:px-12">
                <a href="{{ url('/') }}" class="inline-flex text-ink-950" aria-label="{{ config('app.name') }}">
                    <x-brand.logo />
                </a>
                <p class="mt-10 font-display text-7xl tracking-tight text-teal-900" dir="ltr" style="font-variation-settings: 'opsz' 144">{{ $code }}</p>
                <h1 class="display mt-4 text-3xl text-ink-950">{{ $title }}</h1>
                <p class="mx-auto mt-4 max-w-sm text-base leading-relaxed text-ink-600">{{ $message }}</p>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ url('/') }}" class="btn btn-primary">
                        <span>{{ __('errors.actions.home') }}</span>
                        <span class="btn-icon" aria-hidden="true">
                            <svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </a>
                    @if (in_array((int) $code, [403, 404, 419], true))
                        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-ghost">
                            <span>{{ __('errors.actions.back') }}</span>
                            <span class="btn-icon" aria-hidden="true">
                                <svg class="flip-rtl size-4 rotate-180" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </main>
</x-layouts.site>
