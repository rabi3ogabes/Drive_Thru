@php
    use App\Domain\Menu\MenuLayout;
    use App\Support\Money;
    $layout = MenuLayout::for($restaurant);
    $currency = $restaurant->currency;
    $symbol = trim(explode("\u{00A0}", Money::format(0, $currency))[1] ?? $currency);
    $decimals = Money::decimals($currency);
@endphp
<div x-data="{
        money(minor) { return (minor / 10 ** {{ $decimals }}).toLocaleString('en-US', { minimumFractionDigits: {{ $decimals }}, maximumFractionDigits: {{ $decimals }} }) + ' ' + @js($symbol) },
        q: '',
        matches(el) { return this.q.trim() === '' || el.dataset.search.includes(this.q.trim().toLowerCase()) },
     }">
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" active="menu" :menu-url="url()->current()">
        {{-- Branch header --}}
        <section class="pt-3">
            <a href="{{ route('public.restaurant', $restaurant) }}#branches" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-ink-500">
                <svg class="flip-rtl size-4 rotate-180" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ __('guest.menu.change_branch') }}
            </a>
            <h1 class="display mt-1 text-3xl text-ink-950">{{ $branch->name }}</h1>
            <x-public.branch-status :branch="$branch" :schedule="$schedule" class="mt-2" />

            @php
                $week = $schedule->weekly($branch);
                $today = now($restaurant->timezone)->dayOfWeek;
                $phone = collect($branch->phones ?? [])->first();
            @endphp
            <details class="group mt-3 rounded-2xl bg-white/70 ring-1 ring-ink-950/5 [&_summary::-webkit-details-marker]:hidden">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-2.5 text-sm">
                    <span class="truncate text-ink-600">{{ $branch->address ?: $branch->city ?: __('public.branch.hours') }}</span>
                    <span class="flex shrink-0 items-center gap-1 font-medium text-ink-800">
                        {{ __('public.branch.hours') }}
                        <svg class="size-4 transition group-open:rotate-180" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </summary>
                <div class="border-t border-ink-950/5 px-4 pb-4 pt-3">
                    @if ($week === null)
                        <p class="text-sm text-ink-600">{{ __('public.status.all_day_long') }}</p>
                    @else
                        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                            @foreach (range(0, 6) as $offset)
                                @php $day = ($today + $offset) % 7; @endphp
                                <dt @class(['text-ink-500', 'font-semibold !text-ink-950' => $offset === 0])>{{ \Carbon\Carbon::now()->startOfWeek(\Carbon\CarbonInterface::SUNDAY)->addDays($day)->translatedFormat('l') }}</dt>
                                <dd @class(['text-end tabular-nums text-ink-700', 'font-semibold !text-ink-950' => $offset === 0]) dir="ltr">{{ $week[$day] === [] ? __('public.branch.closed') : implode(' · ', $week[$day]) }}</dd>
                            @endforeach
                        </dl>
                    @endif
                    <div class="mt-3 flex gap-2">
                        @if ($branch->maps_url || $branch->hasLocation())
                            <a href="{{ $branch->maps_url ?: 'https://www.google.com/maps/dir/?api=1&destination='.$branch->latitude.','.$branch->longitude }}" target="_blank" rel="noopener" class="flex-1 rounded-full bg-ivory-100 py-2 text-center text-sm font-semibold text-ink-800">{{ __('public.branch.directions') }}</a>
                        @endif
                        @if ($phone)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="flex-1 rounded-full bg-ivory-100 py-2 text-center text-sm font-semibold text-ink-800">{{ __('guest.home.call') }}</a>
                        @endif
                    </div>
                </div>
            </details>

            @unless ($state->acceptsOrders())
                <p class="mt-4 rounded-2xl bg-brass-50 px-4 py-3 text-sm text-brass-900 ring-1 ring-brass-500/15">
                    {{ __('guest.menu.order_ahead_hint') }}
                </p>
            @endunless
        </section>

        {{-- Search + section chips --}}
        <div class="sticky top-[3.75rem] z-20 -mx-4 mt-5 bg-ivory-50/90 px-4 pb-2 pt-2 backdrop-blur-xl sm:-mx-6 sm:px-6">
            <label class="relative block">
                <span class="sr-only">{{ __('public.menu.search') }}</span>
                <svg class="pointer-events-none absolute start-4 top-1/2 size-4 -translate-y-1/2 text-ink-400" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.5"/><path d="m13.5 13.5 3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <input x-model.debounce.120ms="q" type="search" enterkeyhint="search" placeholder="{{ __('public.menu.search') }}"
                       class="w-full rounded-full border-0 bg-white py-3 pe-4 ps-11 text-[0.9375rem] text-ink-900 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/8 placeholder:text-ink-400 focus:ring-2 focus:ring-[var(--brand)]">
            </label>
            <ul class="mt-2 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]" x-show="q === ''">
                @foreach ($sections as $section)
                    <li class="shrink-0">
                        <a href="#section-{{ $section['category']->id }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-sm font-medium text-ink-700 ring-1 ring-ink-950/8 transition active:scale-95">
                            @if ($section['category']->icon)<span aria-hidden="true">{{ $section['category']->icon }}</span>@endif
                            {{ $section['category']->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Menu --}}
        <div class="mt-3 space-y-8">
            @forelse ($sections as $section)
                @php $category = $section['category']; @endphp
                <section id="section-{{ $category->id }}" class="scroll-mt-40" wire:key="section-{{ $category->id }}">
                    <h2 class="text-lg font-semibold text-ink-950">
                        @if ($category->icon)<span aria-hidden="true" class="me-1">{{ $category->icon }}</span>@endif{{ $category->name }}
                    </h2>
                    @if ($category->description)<p class="mt-0.5 text-sm text-ink-500">{{ $category->description }}</p>@endif

                    <ul @class(['mt-3 grid gap-3', 'sm:grid-cols-2' => $layout->cardStyle === 'grid'])>
                        @foreach ($section['items'] as $entry)
                            @php
                                $product = $entry['product'];
                                $offer = $entry['offer'];
                                $search = mb_strtolower($product->getTranslation('name', 'en', false).' '.$product->getTranslation('name', 'ar', false));
                            @endphp
                            <li x-show="matches($el)" data-search="{{ $search }}" wire:key="p-{{ $product->id }}">
                                <button type="button" wire:click="openProduct({{ $product->id }})"
                                        @class(['group flex w-full overflow-hidden rounded-[1.5rem] bg-white text-start shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5 transition active:scale-[0.985]',
                                                'opacity-60' => ! $offer->isAvailable()])>
                                    @if ($layout->imageStyle !== 'none')
                                        <span class="relative m-2.5 grid size-24 shrink-0 place-items-center overflow-hidden rounded-[1.1rem] bg-ivory-100 text-3xl">
                                            @if ($image = $product->imageUrl(400))
                                                <img src="{{ $image }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                            @else
                                                <span aria-hidden="true">{{ $category->icon ?: '🍽️' }}</span>
                                            @endif
                                        </span>
                                    @endif
                                    <span class="flex min-w-0 flex-1 flex-col p-3.5 ps-1.5">
                                        @if ($layout->showHighlights && ($product->is_popular || $product->is_new || $entry['savings'] > 0))
                                            <span class="mb-1 flex flex-wrap gap-1">
                                                @if ($product->is_popular)<span class="rounded-full bg-brass-50 px-2 py-0.5 text-[0.625rem] font-semibold text-brass-800">{{ __('public.menu.popular') }}</span>@endif
                                                @if ($product->is_new)<span class="rounded-full bg-sage-100 px-2 py-0.5 text-[0.625rem] font-semibold text-teal-900">{{ __('public.menu.new') }}</span>@endif
                                                @if ($entry['savings'] > 0)<span class="rounded-full bg-[var(--brand)] px-2 py-0.5 text-[0.625rem] font-semibold text-white">{{ __('public.menu.save', ['amount' => Money::format($entry['savings'], $currency)]) }}</span>@endif
                                            </span>
                                        @endif
                                        <span class="font-semibold leading-snug text-ink-950">{{ $product->name }}</span>
                                        @if ($layout->showDescriptions && $product->description)
                                            <span class="mt-0.5 line-clamp-2 text-[0.8125rem] leading-relaxed text-ink-500">{{ $product->description }}</span>
                                        @endif
                                        <span class="mt-auto flex items-end justify-between gap-2 pt-2">
                                            <span class="tabular-nums" dir="ltr">
                                                @if ($offer->isDiscounted())<span class="me-1 text-xs text-ink-400 line-through">{{ Money::format($offer->basePriceMinor, $currency) }}</span>@endif
                                                <span class="font-semibold text-ink-950">{{ Money::format($offer->priceMinor, $currency) }}</span>
                                            </span>
                                            @if ($offer->isAvailable())
                                                <span class="grid size-8 place-items-center rounded-full bg-[var(--brand)] text-white shadow-sm" aria-hidden="true">
                                                    <svg wire:loading.remove wire:target="openProduct({{ $product->id }})" class="size-4" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                    <svg wire:loading wire:target="openProduct({{ $product->id }})" class="size-4 animate-spin" viewBox="0 0 16 16" fill="none"><path d="M8 2a6 6 0 1 0 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                </span>
                                            @else
                                                <span class="text-xs font-semibold text-ink-500">{{ $offer->unavailable->getLabel() }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="rounded-3xl bg-white p-8 text-center text-ink-500 ring-1 ring-ink-950/5">{{ __('guest.menu.empty') }}</p>
            @endforelse
        </div>

        {{-- Sticky bag bar --}}
        <x-slot:above>
            @if ($bag && ! $bag->isEmpty())
                <a href="{{ route('customer.checkout', [$restaurant, $branch->code]) }}" wire:navigate
                   class="mb-2 flex items-center justify-between gap-3 rounded-full bg-ink-950 py-2 pe-2 ps-5 text-white shadow-[0_18px_40px_-14px_rgb(10_15_28/0.6)] transition active:scale-[0.99]">
                    <span class="flex items-center gap-3">
                        <span class="grid size-7 place-items-center rounded-full bg-white/15 text-sm font-semibold tabular-nums">{{ $bag->count() }}</span>
                        <span class="font-semibold">{{ __('guest.menu.view_bag') }}</span>
                    </span>
                    <span class="rounded-full bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold tabular-nums" dir="ltr">{{ Money::format($bag->totalMinor, $currency) }}</span>
                </a>
            @endif
        </x-slot:above>
    </x-customer.shell>

    {{-- Product sheet --}}
    @if ($sheet)
        @include('livewire.customer.partials.product-sheet', ['sheet' => $sheet])
    @endif

    {{-- Make it a meal / complete your meal --}}
    @if ($upsell)
        <div class="fixed inset-0 z-50 flex items-end justify-center" role="dialog" aria-modal="true" wire:key="upsell">
            <button type="button" class="absolute inset-0 bg-ink-950/40 backdrop-blur-[2px]" wire:click="dismissUpsell" aria-label="{{ __('guest.close') }}"></button>
            <div class="relative w-full max-w-2xl rounded-t-[2rem] bg-white px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-2xl"
                 x-data x-init="matchMedia('(prefers-reduced-motion: reduce)').matches || $el.animate([{ transform: 'translateY(100%)' }, { transform: 'translateY(0)' }], { duration: 320, easing: 'cubic-bezier(.32,.72,0,1)' })">
                <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-ink-200"></div>
                @if ($upsell['type'] === 'meal')
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brass-700 rtl:tracking-normal">{{ __('guest.upsell.meal_eyebrow') }}</p>
                    <h2 class="display mt-1 text-2xl text-ink-950">{{ __('guest.upsell.meal_title', ['meal' => $upsell['meal']]) }}</h2>
                    <p class="mt-1 text-ink-500">
                        {{ $upsell['savings'] ? __('guest.upsell.meal_body_savings', ['amount' => $upsell['savings']]) : __('guest.upsell.meal_body') }}
                    </p>
                    <div class="mt-5 flex gap-3">
                        <button type="button" wire:click="dismissUpsell" class="flex-1 rounded-full bg-ivory-100 py-3.5 font-semibold text-ink-800">{{ __('guest.upsell.no_thanks') }}</button>
                        <button type="button" wire:click="makeItAMeal({{ $upsell['item_id'] }}, {{ $upsell['meal_id'] }})" wire:loading.attr="disabled"
                                class="flex-[1.4] rounded-full bg-[var(--brand)] py-3.5 font-semibold text-white shadow-lg">{{ __('guest.upsell.make_meal', ['price' => $upsell['price']]) }}</button>
                    </div>
                @else
                    <h2 class="display text-2xl text-ink-950">{{ __('guest.upsell.complete_title') }}</h2>
                    <ul class="mt-4 space-y-2">
                        @foreach ($upsell['items'] as $suggestion)
                            <li>
                                <button type="button" wire:click="quickAdd({{ $suggestion['id'] }})" class="flex w-full items-center gap-3 rounded-2xl bg-ivory-50 p-2.5 text-start ring-1 ring-ink-950/5">
                                    <span class="grid size-12 place-items-center overflow-hidden rounded-xl bg-ivory-100 text-xl">
                                        @if ($suggestion['image'])<img src="{{ $suggestion['image'] }}" alt="" class="size-full object-cover">@else<span aria-hidden="true">🍽️</span>@endif
                                    </span>
                                    <span class="flex-1 font-medium text-ink-900">{{ $suggestion['name'] }}</span>
                                    <span class="text-sm font-semibold tabular-nums text-ink-700" dir="ltr">{{ $suggestion['price'] }}</span>
                                    <span class="grid size-8 place-items-center rounded-full bg-[var(--brand)] text-white" aria-hidden="true"><svg class="size-4" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <button type="button" wire:click="dismissUpsell" class="mt-4 w-full rounded-full bg-ivory-100 py-3.5 font-semibold text-ink-800">{{ __('guest.upsell.continue') }}</button>
                @endif
            </div>
        </div>
    @endif
</div>
