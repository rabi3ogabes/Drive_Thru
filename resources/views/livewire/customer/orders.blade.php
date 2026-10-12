@php
    use App\Domain\Ordering\Enums\OrderStatus;
    use App\Support\Money;
    $tz = $restaurant->timezone;
    $empty = 'mt-8 rounded-[2rem] bg-white px-6 py-10 text-center shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5';
@endphp
<div>
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" active="orders">
        <h1 class="display pt-4 text-3xl text-ink-950">{{ __('guest.orders.title') }}</h1>

        @if (! $customer)
            <div class="{{ $empty }}">
                <div class="mx-auto grid size-16 place-items-center rounded-full bg-ivory-100 text-3xl" aria-hidden="true">🧾</div>
                <p class="mt-4 font-semibold text-ink-950">{{ __('guest.orders.signed_out') }}</p>
                <p class="mt-1 text-ink-500">{{ __('guest.orders.signed_out_hint') }}</p>
                <a href="{{ route('customer.sign-in', ['restaurant' => $restaurant, 'to' => route('customer.orders', $restaurant)]) }}" wire:navigate class="mt-6 inline-flex rounded-full bg-[var(--brand)] px-6 py-3 font-semibold text-white">{{ __('guest.sign_in.title') }}</a>
            </div>
        @elseif ($active->isEmpty() && $past->isEmpty())
            <div class="{{ $empty }}">
                <div class="mx-auto grid size-16 place-items-center rounded-full bg-ivory-100 text-3xl" aria-hidden="true">🍔</div>
                <p class="mt-4 font-semibold text-ink-950">{{ __('guest.orders.empty') }}</p>
                <a href="{{ route('public.restaurant', $restaurant) }}#branches" wire:navigate class="mt-6 inline-flex rounded-full bg-[var(--brand)] px-6 py-3 font-semibold text-white">{{ __('guest.checkout.browse') }}</a>
            </div>
        @else
            @if ($active->isNotEmpty())
                <h2 class="mt-6 text-sm font-semibold uppercase tracking-[0.14em] text-ink-500 rtl:tracking-normal">{{ __('guest.orders.now') }}</h2>
                <ul class="mt-2 space-y-2">
                    @foreach ($active as $order)
                        <li wire:key="a-{{ $order->id }}">
                            <a href="{{ route('customer.order', [$restaurant, $order]) }}" wire:navigate class="flex items-center gap-4 rounded-[1.5rem] bg-ink-950 p-4 text-white shadow-[0_20px_40px_-20px_rgb(10_15_28/0.6)]">
                                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white/10"><x-filament::icon :icon="$order->status->getIcon()" class="size-6 text-brass-200" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-xs text-white/60">{{ __('guest.order.number', ['number' => $order->displayNumber()]) }} · {{ $order->branch->name }}</span>
                                    <span class="block font-semibold">{{ $order->status->getLabel() }}</span>
                                </span>
                                <svg class="flip-rtl size-5 text-white/60" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($past->isNotEmpty())
                <h2 class="mt-8 text-sm font-semibold uppercase tracking-[0.14em] text-ink-500 rtl:tracking-normal">{{ __('guest.orders.past') }}</h2>
                <ul class="mt-2 space-y-2">
                    @foreach ($past as $order)
                        <li wire:key="p-{{ $order->id }}" class="rounded-[1.5rem] bg-white p-4 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5">
                            <a href="{{ route('customer.order', [$restaurant, $order]) }}" wire:navigate class="block">
                                <div class="flex items-baseline justify-between gap-3">
                                    <p class="text-sm text-ink-500"><span class="tabular-nums">{{ $order->placed_at?->setTimezone($tz)->translatedFormat('j M, H:i') }}</span> · {{ $order->branch->name }}</p>
                                    <p @class(['text-xs font-semibold', 'text-ink-400' => $order->status === OrderStatus::Cancelled, 'text-teal-800' => $order->status !== OrderStatus::Cancelled])>{{ $order->status->getLabel() }}</p>
                                </div>
                                <p class="mt-1 line-clamp-2 font-medium text-ink-950">{{ $order->items->map(fn ($i) => $i->quantity.'× '.$i->localName())->join('، ') }}</p>
                            </a>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="font-semibold tabular-nums text-ink-950" dir="ltr">{{ Money::format($order->total_minor, $order->currency) }}</span>
                                <button type="button" wire:click="orderAgain({{ $order->id }})" wire:loading.attr="disabled" class="rounded-full bg-ivory-100 px-4 py-2 text-sm font-semibold text-ink-900 transition active:scale-95 disabled:opacity-60">{{ __('guest.order.again') }}</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($hasMore)
                <button type="button" wire:click="more" class="mt-4 w-full rounded-full py-3 text-sm font-semibold text-ink-600">{{ __('guest.orders.more') }}</button>
            @endif
        @endif
    </x-customer.shell>
</div>
