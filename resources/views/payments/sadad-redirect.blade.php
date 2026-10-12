@php use App\Support\Money; @endphp
<x-layouts.customer :restaurant="$restaurant" :title="__('guest.pay.title')">
    <main id="main" class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-10 text-center">
        <div class="rounded-[2rem] bg-white p-1.5 shadow-[0_30px_60px_-30px_rgb(10_15_28/0.35)] ring-1 ring-ink-950/5">
            <div class="rounded-[calc(2rem-0.375rem)] bg-gradient-to-br from-ivory-100 to-white px-6 pb-7 pt-8">
                <x-public.restaurant-logo :restaurant="$restaurant" class="mx-auto size-14" />
                <p class="mt-5 text-sm text-ink-500">{{ __('guest.pay.paying', ['restaurant' => $restaurant->name]) }}</p>
                <p class="display mt-1 text-4xl tabular-nums text-ink-950" dir="ltr">{{ Money::format($order->total_minor, $order->currency) }}</p>
                <p class="mt-6 flex items-center justify-center gap-2 text-ink-600" role="status">
                    <svg class="size-4 animate-spin text-[var(--brand)]" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 2a6 6 0 1 0 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    {{ __('guest.pay.to_sadad') }}
                </p>
                <form id="sadad" method="POST" action="{{ $action }}" class="mt-6">
                    @foreach ($fields as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" class="w-full rounded-full bg-[var(--brand)] py-3.5 font-semibold text-white shadow-lg">{{ __('guest.pay.continue_sadad') }}</button>
                </form>
            </div>
        </div>
        <p class="mt-5 text-xs leading-relaxed text-ink-400">{{ __('guest.pay.secure_note') }}</p>
    </main>
    <script>document.getElementById('sadad').submit();</script>
</x-layouts.customer>
