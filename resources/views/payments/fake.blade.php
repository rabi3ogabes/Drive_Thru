@php
    use App\Support\Money;
    $order = $payment->order;
    $restaurant = $order->restaurant;
@endphp
<x-layouts.customer :restaurant="$restaurant" :title="__('guest.pay.title')">
    <main id="main" class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-10">
        <div class="rounded-[2rem] bg-white p-1.5 shadow-[0_30px_60px_-30px_rgb(10_15_28/0.35)] ring-1 ring-ink-950/5">
            <div class="rounded-[calc(2rem-0.375rem)] bg-gradient-to-br from-ivory-100 to-white px-6 pb-6 pt-7 text-center">
                <span class="inline-flex rounded-full bg-brass-50 px-3 py-1 text-[0.6875rem] font-semibold uppercase tracking-[0.16em] text-brass-800 ring-1 ring-brass-500/15 rtl:tracking-normal">{{ __('guest.pay.test_mode') }}</span>
                <x-public.restaurant-logo :restaurant="$restaurant" class="mx-auto mt-5 size-14" />
                <p class="mt-4 text-sm text-ink-500">{{ __('guest.pay.paying', ['restaurant' => $restaurant->name]) }}</p>
                <p class="display mt-1 text-4xl tabular-nums text-ink-950" dir="ltr">{{ Money::format($payment->amount_minor, $payment->currency) }}</p>
                <p class="mt-1 text-sm text-ink-500">{{ __('guest.order.number', ['number' => $order->displayNumber()]) }} · {{ $payment->method->getLabel() }}</p>

                <div class="mt-7 grid gap-2">
                    <form method="POST" action="{{ route('payments.fake.decide', $payment->reference) }}">
                        @csrf
                        <input type="hidden" name="return" value="{{ $return }}">
                        <button name="decision" value="approve" class="w-full rounded-full bg-[var(--brand)] py-3.5 font-semibold text-white shadow-lg transition active:scale-[0.98]">{{ __('guest.pay.approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('payments.fake.decide', $payment->reference) }}">
                        @csrf
                        <input type="hidden" name="return" value="{{ $return }}">
                        <button name="decision" value="decline" class="w-full rounded-full py-3 text-sm font-semibold text-ink-500 transition hover:text-ink-900">{{ __('guest.pay.decline') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <p class="mt-5 text-center text-xs leading-relaxed text-ink-400">{{ __('guest.pay.note') }}</p>
    </main>
</x-layouts.customer>
