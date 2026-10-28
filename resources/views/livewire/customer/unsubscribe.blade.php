<div>
    <style>@keyframes unsubscribe-pop { from { transform: scale(0.6); opacity: 0; } to { transform: none; opacity: 1; } }</style>
    <x-customer.shell :restaurant="$restaurant" :customer="null" bare>
        <section class="mx-auto max-w-md pt-10">
            <div class="rounded-[2rem] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5">
                <div class="rounded-[calc(2rem-0.375rem)] bg-gradient-to-br from-ivory-100 to-white px-5 pb-6 pt-7" wire:key="unsubscribe-{{ $state }}">
                    @if ($state === 'done' || $state === 'already')
                        <span class="grid size-14 place-items-center rounded-full bg-[var(--brand)] text-white motion-safe:animate-[unsubscribe-pop_480ms_cubic-bezier(0.32,0.72,0,1)_both]">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    @else
                        <x-public.restaurant-logo :restaurant="$restaurant" class="size-14" />
                    @endif

                    <h1 class="display mt-5 text-3xl text-ink-950">{{ __('campaigns.unsubscribe.'.match ($state) { 'ask' => 'title', 'done' => 'done_title', 'already' => 'already_title', default => 'invalid_title' }, ['restaurant' => $restaurant->name]) }}</h1>
                    <p class="mt-1.5 text-ink-500">{{ __('campaigns.unsubscribe.'.match ($state) { 'ask' => 'body', 'done' => 'done_body', 'already' => 'already_body', default => 'invalid_body' }, ['restaurant' => $restaurant->name]) }}</p>

                    @if ($state === 'ask')
                        <button type="button" wire:click="stop" wire:loading.attr="disabled"
                                class="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-ink-950 px-6 py-3.5 text-[0.9375rem] font-semibold text-white transition active:scale-[0.98] disabled:opacity-60">
                            <svg wire:loading wire:target="stop" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                            {{ __('campaigns.unsubscribe.confirm') }}
                        </button>
                        <a href="{{ route('public.restaurant', $restaurant) }}" wire:navigate class="mt-3 block rounded-full px-6 py-3 text-center text-sm font-medium text-ink-600 ring-1 ring-ink-950/10 transition active:scale-[0.98]">{{ __('campaigns.unsubscribe.keep') }}</a>
                    @endif
                </div>
            </div>
            @if ($state !== 'ask')
                <a href="{{ route('public.restaurant', $restaurant) }}" wire:navigate class="mt-6 block text-center text-sm font-medium text-ink-500">{{ __('campaigns.unsubscribe.back', ['restaurant' => $restaurant->name]) }}</a>
            @endif
        </section>
    </x-customer.shell>
</div>
