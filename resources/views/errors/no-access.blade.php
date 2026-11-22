{{-- A signed-in account linked to no restaurant and no platform role: say so, and offer to sign out. --}}
<x-layouts.error code="403" :title="__('errors.no_access.title')" :message="__('errors.no_access.message')">
    <x-slot:actions>
        <form method="POST" action="{{ $logoutUrl }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                <span>{{ __('errors.no_access.sign_out') }}</span>
                <span class="btn-icon" aria-hidden="true">
                    <svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </button>
        </form>
        <a href="{{ url('/') }}" class="btn btn-ghost">
            <span>{{ __('errors.actions.home') }}</span>
            <span class="btn-icon" aria-hidden="true">
                <svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
        </a>
    </x-slot:actions>
</x-layouts.error>
