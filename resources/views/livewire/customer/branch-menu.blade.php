@php
    use App\Domain\Menu\MenuLayout;
    use App\Support\Money;
    use Illuminate\Support\Facades\Storage;
    $layout = MenuLayout::for($restaurant);
    $currency = $restaurant->currency;
    $symbol = trim(explode("\u{00A0}", Money::format(0, $currency))[1] ?? $currency);
    $decimals = Money::decimals($currency);
    $week = $schedule->weekly($branch);
    $today = now($restaurant->timezone)->dayOfWeek;
    $phone = collect($branch->phones ?? [])->first();
    $directions = $branch->maps_url ?: ($branch->hasLocation() ? 'https://www.google.com/maps/dir/?api=1&destination='.$branch->latitude.','.$branch->longitude : null);
    $entries = collect($sections)->flatMap(fn (array $section) => $section['items']);
    $signature = $entries->filter(fn (array $e): bool => $e['product']->is_popular && $e['product']->image_path !== null && $e['offer']->isAvailable())->take(8)->values();
    $cover = $restaurant->cover_path ? Storage::disk('public')->url($restaurant->cover_path) : null;
    $collage = $cover === null ? $entries->filter(fn (array $e): bool => $e['product']->image_path !== null)->take(3)->map(fn (array $e): ?string => $e['product']->imageUrl(800))->values() : collect();
    $count = $entries->count();
@endphp
<div x-data="{
        money(minor) { return (minor / 10 ** {{ $decimals }}).toLocaleString('en-US', { minimumFractionDigits: {{ $decimals }}, maximumFractionDigits: {{ $decimals }} }) + ' ' + @js($symbol) },
        q: '',
        active: null,
        matches(el) { return this.q.trim() === '' || el.dataset.search.includes(this.q.trim().toLowerCase()) },
        watchSections() {
            const io = new IntersectionObserver((seen) => seen.forEach((e) => { if (e.isIntersecting) this.setActive(e.target.dataset.section) }), { rootMargin: '-45% 0px -50% 0px' });
            document.querySelectorAll('[data-section]').forEach((s) => io.observe(s));
        },
        setActive(id) {
            this.active = id;
            const chip = this.$refs.chips?.querySelector(`[data-chip='${id}']`);
            const rail = this.$refs.chips;
            if (chip && rail) rail.scrollTo({ left: chip.offsetLeft - rail.clientWidth / 2 + chip.clientWidth / 2, behavior: 'smooth' });
        },
     }" x-init="watchSections()">
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" active="menu" :menu-url="url()->current()" wide>
        {{-- Hero: the place, its state, and the three things guests need --}}
        <section class="pt-2" aria-labelledby="branch-title">
            <div class="rounded-[2.25rem] bg-white/60 p-1.5 shadow-[0_30px_60px_-40px_color-mix(in_oklab,var(--color-ink-950)_55%,transparent)] ring-1 ring-ink-950/5">
                <div class="relative isolate overflow-hidden rounded-[calc(2.25rem-0.375rem)] bg-[var(--brand)]">
                    {{-- Photography: the restaurant's cover, or its dishes --}}
                    @if ($cover)
                        <img src="{{ $cover }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" fetchpriority="high">
                    @elseif ($collage->isNotEmpty())
                        <div aria-hidden="true" @class(['absolute inset-0 -z-10 grid gap-0.5', 'sm:grid-cols-3' => $collage->count() === 3, 'sm:grid-cols-2' => $collage->count() === 2])>
                            @foreach ($collage as $i => $photo)
                                <img src="{{ $photo }}" alt="" @if ($i === 0) fetchpriority="high" @endif @class(['size-full object-cover motion-safe:animate-[menu-drift_18s_ease-in-out_infinite_alternate]', 'hidden sm:block' => $i > 0]) style="animation-delay: -{{ $i * 6 }}s">
                            @endforeach
                        </div>
                    @endif
                    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,color-mix(in_oklab,var(--color-ink-950)_15%,transparent)_0%,color-mix(in_oklab,var(--color-ink-950)_35%,transparent)_45%,color-mix(in_oklab,var(--color-ink-950)_82%,transparent)_100%)]"></div>

                    <div class="flex min-h-[19rem] flex-col justify-between p-5 text-white sm:min-h-[22rem] sm:p-7 lg:min-h-[24rem] lg:p-9">
                        <a href="{{ route('public.restaurant', $restaurant) }}#branches" wire:navigate
                           class="inline-flex w-max items-center gap-1.5 rounded-full bg-white/15 px-3.5 py-1.5 text-xs font-medium text-white ring-1 ring-white/25 backdrop-blur-md transition hover:bg-white/25">
                            <svg class="flip-rtl size-3.5 rotate-180" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ __('guest.menu.change_branch') }}
                        </a>

                        <div class="mt-10">
                            <p class="text-[0.6875rem] font-medium uppercase tracking-[0.24em] text-white/70 rtl:tracking-normal">{{ $restaurant->name }}@if ($branch->city) · {{ $branch->city }}@endif</p>
                            <h1 id="branch-title" class="display mt-1.5 text-[2.5rem] leading-[1.05] text-white sm:text-5xl lg:text-6xl rtl:leading-[1.3]">{{ $branch->name }}</h1>
                            <div class="mt-4 inline-flex rounded-full bg-white/92 px-1.5 py-1 shadow-sm ring-1 ring-white/40">
                                <x-public.branch-status :branch="$branch" :schedule="$schedule" class="pe-2" />
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <button type="button" x-on:click="$refs.hours.open = ! $refs.hours.open; $refs.hours.open && $nextTick(() => $refs.hours.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
                                        class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white/15 px-4 text-sm font-semibold ring-1 ring-white/25 backdrop-blur-md transition hover:bg-white/25 active:scale-[0.97]">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2" stroke-linecap="round"/></svg>
                                    {{ __('public.branch.hours') }}
                                </button>
                                @if ($directions)
                                    <a href="{{ $directions }}" target="_blank" rel="noopener"
                                       class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white/15 px-4 text-sm font-semibold ring-1 ring-white/25 backdrop-blur-md transition hover:bg-white/25 active:scale-[0.97]">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 21s-6.5-5.4-6.5-11a6.5 6.5 0 1 1 13 0c0 5.6-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>
                                        {{ __('public.branch.directions') }}
                                    </a>
                                @endif
                                @if ($phone)
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}"
                                       class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white/15 px-4 text-sm font-semibold ring-1 ring-white/25 backdrop-blur-md transition hover:bg-white/25 active:scale-[0.97]">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 4h3l1.5 4.5-2 1.5a11 11 0 0 0 6.5 6.5l1.5-2L20 16v3a1 1 0 0 1-1 1A15 15 0 0 1 4 5a1 1 0 0 1 1-1Z" stroke-linejoin="round"/></svg>
                                        {{ __('guest.home.call') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Opening hours, folded away until asked for --}}
            <details x-ref="hours" class="group mt-3 scroll-mt-24 rounded-[1.5rem] bg-white/80 ring-1 ring-ink-950/5 [&_summary::-webkit-details-marker]:hidden">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-5 text-sm">
                    <span class="truncate text-ink-600">{{ $branch->address ?: $branch->city ?: __('public.branch.hours') }}</span>
                    <span class="flex shrink-0 items-center gap-1 font-medium text-ink-800">
                        {{ __('public.branch.hours') }}
                        <svg class="size-4 transition group-open:rotate-180" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </summary>
                <div class="border-t border-ink-950/5 px-5 pb-5 pt-3">
                    @if ($week === null)
                        <p class="text-sm text-ink-600">{{ __('public.status.all_day_long') }}</p>
                    @else
                        <dl class="grid max-w-md grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
                            @foreach (range(0, 6) as $offset)
                                @php $day = ($today + $offset) % 7; @endphp
                                <dt @class(['text-ink-500', 'font-semibold !text-ink-950' => $offset === 0])>{{ \Carbon\Carbon::now()->startOfWeek(\Carbon\CarbonInterface::SUNDAY)->addDays($day)->translatedFormat('l') }}</dt>
                                <dd @class(['text-end tabular-nums text-ink-700', 'font-semibold !text-ink-950' => $offset === 0]) dir="ltr">{{ $week[$day] === [] ? __('public.branch.closed') : implode(' · ', $week[$day]) }}</dd>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </details>

            @unless ($ordering)
                <p class="mt-3 rounded-[1.5rem] bg-ivory-100 px-5 py-3.5 text-sm text-ink-700 ring-1 ring-ink-950/5">{{ __('guest.menu.menu_only') }}</p>
            @elseunless ($state->acceptsOrders())
                <p class="mt-3 rounded-[1.5rem] bg-brass-50 px-5 py-3.5 text-sm text-brass-900 ring-1 ring-brass-500/15">{{ __('guest.menu.order_ahead_hint') }}</p>
            @endunless
        </section>

        {{-- Most loved: the dishes this kitchen is proud of, large --}}
        @if ($signature->count() >= 2)
            <section class="mt-9" aria-labelledby="loved-title" x-show="q === ''">
                <div class="flex items-end justify-between gap-4 px-1">
                    <div>
                        <p class="text-[0.6875rem] font-medium uppercase tracking-[0.22em] text-brass-700 rtl:tracking-normal">{{ __('guest.menu.signature_eyebrow') }}</p>
                        <h2 id="loved-title" class="display mt-1 text-2xl text-ink-950 sm:text-3xl">{{ __('guest.menu.signature') }}</h2>
                    </div>
                </div>
                <ul class="-mx-4 mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-px-4 px-4 pb-2 [scrollbar-width:none] sm:-mx-6 sm:scroll-px-6 sm:px-6 lg:mx-0 lg:grid lg:grid-cols-4 lg:overflow-visible lg:px-0">
                    @foreach ($signature as $entry)
                        @php $product = $entry['product']; $offer = $entry['offer']; @endphp
                        <li class="w-[68%] shrink-0 snap-start sm:w-[42%] lg:w-auto" wire:key="loved-{{ $product->id }}">
                            <button type="button" wire:click="openProduct({{ $product->id }})"
                                    class="group relative block aspect-[4/5] w-full overflow-hidden rounded-[1.75rem] bg-ivory-100 text-start shadow-[0_24px_40px_-28px_color-mix(in_oklab,var(--color-ink-950)_60%,transparent)] ring-1 ring-ink-950/5 transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] active:scale-[0.98]">
                                <img src="{{ $product->imageUrl(800) }}" alt="" loading="lazy" decoding="async"
                                     class="absolute inset-0 size-full object-cover transition duration-700 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:scale-[1.04]">
                                <span aria-hidden="true" class="absolute inset-0 bg-[linear-gradient(180deg,transparent_45%,color-mix(in_oklab,var(--color-ink-950)_80%,transparent)_100%)]"></span>
                                <span class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-4 text-white">
                                    <span class="min-w-0">
                                        <span class="block truncate text-base font-semibold">{{ $product->name }}</span>
                                        <span class="mt-0.5 block text-sm tabular-nums text-white/80" dir="ltr">{{ Money::format($offer->priceMinor, $currency) }}</span>
                                    </span>
                                    @if ($ordering)
                                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white text-ink-950 shadow-lg transition duration-500 group-hover:scale-105" aria-hidden="true">
                                            <svg class="size-4" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        </span>
                                    @endif
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Search and sections, always within reach --}}
        <div class="sticky top-[3.75rem] z-20 -mx-4 mt-8 bg-ivory-50/85 px-4 pb-2.5 pt-2.5 backdrop-blur-xl sm:-mx-6 sm:px-6">
            <div class="flex items-center gap-3">
                <label class="relative block flex-1 lg:max-w-sm">
                    <span class="sr-only">{{ __('public.menu.search') }}</span>
                    <svg class="pointer-events-none absolute start-4 top-1/2 size-4 -translate-y-1/2 text-ink-400" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.5"/><path d="m13.5 13.5 3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <input x-model.debounce.120ms="q" type="search" enterkeyhint="search" placeholder="{{ __('public.menu.search') }}"
                           class="w-full rounded-full border-0 bg-white py-3 pe-4 ps-11 text-[0.9375rem] text-ink-900 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/8 placeholder:text-ink-400 focus:ring-2 focus:ring-[var(--brand)]">
                </label>
                <span class="hidden shrink-0 text-sm text-ink-500 lg:block">{{ trans_choice('guest.menu.count', $count, ['count' => $count]) }}</span>
            </div>
            <ul x-ref="chips" class="relative mt-2.5 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]" x-show="q === ''">
                @foreach ($sections as $section)
                    @php $id = (string) $section['category']->id; @endphp
                    <li class="shrink-0">
                        <a href="#section-{{ $id }}" data-chip="{{ $id }}" x-on:click="setActive('{{ $id }}')"
                           :class="active === '{{ $id }}' ? 'bg-ink-950 text-white ring-ink-950' : 'bg-white text-ink-700 ring-ink-950/8 hover:bg-ivory-100'"
                           class="inline-flex min-h-10 items-center gap-1.5 rounded-full px-4 text-sm font-medium ring-1 transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] active:scale-95">
                            @if ($section['category']->icon)<span aria-hidden="true">{{ $section['category']->icon }}</span>@endif
                            {{ $section['category']->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- The menu --}}
        <div class="mt-4 space-y-12">
            @forelse ($sections as $section)
                @php $category = $section['category']; @endphp
                <section id="section-{{ $category->id }}" data-section="{{ $category->id }}" class="scroll-mt-44" wire:key="section-{{ $category->id }}" aria-labelledby="section-title-{{ $category->id }}">
                    <header class="flex items-end justify-between gap-4 border-b border-ink-950/6 pb-3" x-show="q === ''">
                        <div class="min-w-0">
                            <h2 id="section-title-{{ $category->id }}" class="display text-2xl text-ink-950 sm:text-[1.75rem]">{{ $category->name }}</h2>
                            @if ($category->description)<p class="mt-1 text-sm text-ink-500">{{ $category->description }}</p>@endif
                        </div>
                        <span class="shrink-0 text-xs tabular-nums text-ink-400">{{ trans_choice('guest.menu.count', count($section['items']), ['count' => count($section['items'])]) }}</span>
                    </header>

                    @if ($layout->cardStyle === 'grid')
                        <ul class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
                            @foreach ($section['items'] as $entry)
                                @include('livewire.customer.partials.menu-card', ['entry' => $entry, 'category' => $category, 'compact' => false])
                            @endforeach
                        </ul>
                    @else
                        <ul class="mt-4 grid gap-3 lg:grid-cols-2">
                            @foreach ($section['items'] as $entry)
                                @include('livewire.customer.partials.menu-card', ['entry' => $entry, 'category' => $category, 'compact' => true])
                            @endforeach
                        </ul>
                    @endif
                </section>
            @empty
                <div class="rounded-[2rem] bg-white p-10 text-center ring-1 ring-ink-950/5">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-ivory-100 text-2xl" aria-hidden="true">🍽️</span>
                    <p class="mt-4 text-ink-600">{{ __('guest.menu.empty') }}</p>
                </div>
            @endforelse

            @if ($count > 0)
                <p x-show="q !== '' && ! [...document.querySelectorAll('[data-search]')].some((el) => matches(el))" x-cloak
                   class="rounded-[2rem] bg-white p-8 text-center text-ink-500 ring-1 ring-ink-950/5">{{ __('guest.menu.no_results') }}</p>
            @endif
        </div>

        {{-- Sticky bag bar --}}
        <x-slot:above>
            @if ($bag && ! $bag->isEmpty())
                <a href="{{ route('customer.checkout', [$restaurant, $branch->code]) }}" wire:navigate
                   class="mb-2 flex items-center justify-between gap-3 rounded-full bg-ink-950 py-2 pe-2 ps-5 text-white shadow-[0_18px_40px_-14px_color-mix(in_oklab,var(--color-ink-950)_60%,transparent)] transition active:scale-[0.99]">
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

    <style>
        @keyframes menu-drift { from { transform: scale(1.02); } to { transform: scale(1.1) translate3d(-1.5%, -1%, 0); } }
    </style>
</div>
