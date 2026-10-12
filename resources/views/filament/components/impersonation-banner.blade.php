@php
    $impersonation = app(\App\Domain\Access\Impersonation::class);
@endphp
@if ($impersonation->isActive())
    @php($impersonator = $impersonation->impersonator())
    {{-- A floating pill: always visible while viewing as the owner, never covering page content. --}}
    <div role="status" aria-live="polite"
         class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4 sm:bottom-6">
        <div class="pointer-events-auto flex max-w-full flex-wrap items-center justify-center gap-x-4 gap-y-2 rounded-full bg-gradient-to-r from-brass-700 via-brass-600 to-brass-700 py-2 ps-5 pe-2 text-sm text-white shadow-[0_20px_40px_-12px_rgb(94_71_47/0.55),inset_0_1px_0_rgb(255_255_255/0.18)] ring-1 ring-black/10">
            <span class="inline-flex min-w-0 items-center gap-2 font-medium">
                <x-filament::icon icon="heroicon-m-eye" class="size-4 shrink-0 opacity-90" />
                <span class="truncate">{{ __('clients.impersonation.banner', ['user' => auth()->user()?->name, 'admin' => $impersonator?->name]) }}</span>
            </span>
            @if ($reason = $impersonation->reason())
                <span class="hidden max-w-xs truncate opacity-80 lg:inline">· {{ $reason }}</span>
            @endif
            <form method="POST" action="{{ route('impersonation.leave') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3.5 py-1.5 font-medium ring-1 ring-white/25 transition hover:bg-white/25 focus-visible:outline-2 focus-visible:outline-white">
                    <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="flip-rtl size-4" />
                    {{ __('clients.impersonation.leave') }}
                </button>
            </form>
        </div>
    </div>
@endif
