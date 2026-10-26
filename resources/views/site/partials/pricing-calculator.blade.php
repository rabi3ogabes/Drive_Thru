@php
    $check = '<svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $arrow = '<svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $forSale = $services->where('for_sale', true);
    $picked = array_values(array_intersect($forSale->keys()->all(), $picked ?? $forSale->keys()->all()));
@endphp
{{-- The real price list, worked out live; "book" hands the choice to the demo form. --}}
<div x-data="{
            prices: {{ \Illuminate\Support\Js::from($forSale->mapWithKeys(fn (array $s): array => [$s['key'] => $s['price']])) }},
            picked: {{ \Illuminate\Support\Js::from($picked) }},
            branches: 1,
            two: {{ $pricing['two'] }}, three: {{ $pricing['three'] }},
            toggle(key) { this.picked = this.picked.includes(key) ? this.picked.filter((k) => k !== key) : [...this.picked, key] },
            get subtotal() { return this.picked.reduce((sum, key) => sum + (this.prices[key] ?? 0) * this.branches, 0) },
            get rate() { return this.picked.length >= 3 ? this.three : (this.picked.length === 2 ? this.two : 0) },
            get discount() { return Math.round(this.subtotal * this.rate / 10000) },
            get total() { return this.subtotal - this.discount },
            money(minor) { return (minor / 10 ** {{ $pricing['decimals'] }}).toLocaleString('en-US', { minimumFractionDigits: {{ $pricing['decimals'] }}, maximumFractionDigits: {{ $pricing['decimals'] }} }) + ' ' + {{ \Illuminate\Support\Js::from($pricing['symbol']) }} },
            book() { window.dispatchEvent(new CustomEvent('pick-services', { detail: { services: this.picked, branches: this.branches } })); document.getElementById('request')?.scrollIntoView({ behavior: 'smooth' }) },
         }">
<div class="grid gap-5 lg:grid-cols-12">
    <ul class="grid gap-3 lg:col-span-7" data-reveal>
        @foreach ($forSale as $key => $service)
            <li>
                <button type="button" x-on:click="toggle('{{ $key }}')" :aria-pressed="picked.includes('{{ $key }}')"
                        :class="picked.includes('{{ $key }}') ? 'bg-white ring-2 ring-teal-800 shadow-[var(--shadow-lift)]' : 'bg-white/60 ring-1 ring-ink-950/8 hover:bg-white'"
                        class="flex w-full items-center gap-4 rounded-[1.6rem] p-4 text-start transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] active:scale-[0.99] sm:p-5">
                    <span :class="picked.includes('{{ $key }}') ? 'bg-teal-900 text-brass-200' : 'bg-ink-100 text-ink-500'" class="grid size-12 shrink-0 place-items-center rounded-2xl transition">
                        <x-filament::icon :icon="\App\Domain\Services\Enums\Service::from($key)->getIcon()" class="size-6" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-ink-950">{{ $service['label'] }}</span>
                        <span class="mt-0.5 block text-sm text-ink-500">{{ __('services.taglines.'.$key) }}</span>
                    </span>
                    <span class="text-end">
                        <span class="block font-semibold tabular-nums text-ink-950" dir="ltr">{{ $service['price_label'] }}</span>
                        <span class="block text-xs text-ink-500">{{ __('site.services.per') }}</span>
                    </span>
                    <span :class="picked.includes('{{ $key }}') ? 'bg-teal-900 text-paper ring-teal-900' : 'bg-white text-transparent ring-ink-950/15'" class="grid size-6 shrink-0 place-items-center rounded-full ring-1 transition">{!! $check !!}</span>
                </button>
            </li>
        @endforeach
        <li class="flex items-center justify-between gap-4 rounded-[1.6rem] bg-white/60 p-4 ring-1 ring-ink-950/8 sm:p-5">
            <span class="font-semibold text-ink-950">{{ __('site.pricing.branches') }}</span>
            <span class="flex items-center rounded-full bg-ivory-100 p-1 ring-1 ring-ink-950/5">
                <button type="button" x-on:click="branches = Math.max(1, branches - 1)" :disabled="branches <= 1" class="grid size-10 place-items-center rounded-full text-xl text-ink-800 transition enabled:hover:bg-white disabled:opacity-30" aria-label="{{ __('site.pricing.less') }}">−</button>
                <span class="w-10 text-center text-lg font-semibold tabular-nums text-ink-950" x-text="branches" dir="ltr" aria-live="polite">1</span>
                <button type="button" x-on:click="branches = Math.min(50, branches + 1)" class="grid size-10 place-items-center rounded-full text-xl text-ink-800 transition hover:bg-white" aria-label="{{ __('site.pricing.more') }}">+</button>
            </span>
        </li>
    </ul>

    <div class="lg:col-span-5" data-reveal style="--i:1">
        <div class="theme-fixed sticky top-28 rounded-[2rem] bg-ink-950 p-1.5 shadow-[var(--shadow-float)]">
            <div class="rounded-[calc(2rem-0.375rem)] bg-[radial-gradient(120%_80%_at_100%_0%,color-mix(in_oklab,var(--color-teal-800)_55%,transparent),transparent)] p-6 text-white sm:p-8">
                <template x-if="picked.length === 0">
                    <p class="py-10 text-center text-white/60">{{ __('site.pricing.pick') }}</p>
                </template>
                <div x-show="picked.length > 0">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between text-white/70"><dt>{{ __('site.pricing.subtotal') }}</dt><dd class="tabular-nums" dir="ltr" x-text="money(subtotal)"></dd></div>
                        <div class="flex justify-between text-brass-200" x-show="discount > 0" x-cloak>
                            <dt x-text="{{ \Illuminate\Support\Js::from(__('site.pricing.bundle', ['percent' => '__P__'])) }}.replace('__P__', (rate / 100).toString())"></dt>
                            <dd class="tabular-nums" dir="ltr" x-text="'−' + money(discount)"></dd>
                        </div>
                    </dl>
                    <div class="mt-6 border-t border-white/10 pt-6">
                        <p class="text-sm text-white/60">{{ __('site.pricing.total') }}</p>
                        <p class="mt-1 text-5xl font-semibold tabular-nums tracking-tight" dir="ltr" x-text="money(total)"></p>
                    </div>
                </div>
                <p class="mt-6 text-sm text-white/60">{{ __('site.pricing.bundle_hint', ['two' => rtrim(rtrim(number_format($pricing['two'] / 100, 2), '0'), '.'), 'three' => rtrim(rtrim(number_format($pricing['three'] / 100, 2), '0'), '.')]) }}</p>
                <button type="button" x-on:click="book()" :disabled="picked.length === 0"
                        class="mt-7 flex min-h-14 w-full items-center justify-between gap-3 rounded-full bg-brass-200 py-2 pe-2 ps-6 font-semibold text-teal-900 transition hover:bg-brass-100 active:scale-[0.99] disabled:opacity-50">
                    <span>{{ __('site.pricing.cta') }}</span>
                    <span class="grid size-10 place-items-center rounded-full bg-teal-900 text-brass-200">{!! $arrow !!}</span>
                </button>
            </div>
        </div>
    </div>
</div>
<p class="mx-auto mt-6 max-w-2xl text-center text-sm text-ink-500" data-reveal>{{ __('site.pricing.note', ['currency' => $pricing['currency'], 'days' => $demoDays]) }}</p>
</div>
