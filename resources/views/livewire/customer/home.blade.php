@php
    use App\Domain\Branches\Links;
    use App\Support\Money;
    $links = app(Links::class);
    $menuUrl = $featuredBranch ? $links->branch($restaurant, $featuredBranch) : null;
    $busyTone = ['quiet' => 'bg-sage-100 text-teal-900', 'moderate' => 'bg-brass-50 text-brass-900', 'busy' => 'bg-ivory-200 text-ink-800'];
@endphp
<div>
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" active="home" :menu-url="$menuUrl">
        {{-- Greeting --}}
        <section class="pt-4">
            <p class="text-sm font-medium text-ink-500">{{ $customer ? __('guest.home.welcome_back') : __('guest.home.welcome') }}</p>
            <h1 class="display mt-1 text-[2rem] leading-tight text-ink-950">{{ $greeting }}</h1>
        </section>

        {{-- An order in progress always comes first --}}
        @if ($active)
            <a href="{{ route('customer.order', [$restaurant, $active]) }}" wire:navigate
               class="mt-6 flex items-center gap-4 rounded-[1.5rem] bg-ink-950 p-4 text-white shadow-[0_20px_40px_-20px_rgb(10_15_28/0.6)]">
                <span class="relative grid size-12 shrink-0 place-items-center rounded-2xl bg-white/10">
                    <span class="absolute inset-0 animate-ping rounded-2xl bg-[var(--brand)] opacity-20"></span>
                    <x-filament::icon :icon="$active->status->getIcon()" class="relative size-6 text-brass-200" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-xs text-white/60">{{ __('guest.home.active_order', ['number' => $active->displayNumber()]) }}</span>
                    <span class="block font-semibold">{{ $active->status->getLabel() }}</span>
                </span>
                <svg class="flip-rtl size-5 text-white/60" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        @endif

        {{-- Your usual? --}}
        @if ($last && ! $active)
            <section class="mt-6 overflow-hidden rounded-[1.75rem] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5">
                <div class="rounded-[1.4rem] bg-gradient-to-br from-ivory-100 to-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brass-700 rtl:tracking-normal">{{ __('guest.home.usual') }}</p>
                    <p class="mt-1.5 line-clamp-2 font-semibold text-ink-950">
                        {{ $last->items->map(fn ($i) => $i->quantity.'× '.$i->localName())->join('، ') }}
                    </p>
                    <p class="mt-1 text-sm text-ink-500">{{ $last->branch->name }} · <span dir="ltr" class="tabular-nums">{{ Money::format($last->total_minor, $last->currency) }}</span></p>
                    <button type="button" wire:click="orderUsual" wire:loading.attr="disabled"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-full bg-[var(--brand)] py-3.5 text-[0.9375rem] font-semibold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-70">
                        <span wire:loading.remove wire:target="orderUsual">{{ __('guest.home.order_usual') }}</span>
                        <span wire:loading wire:target="orderUsual">{{ __('guest.loading') }}</span>
                    </button>
                </div>
            </section>
        @endif

        {{-- Favourites, popular, new --}}
        @foreach (['favorites' => __('guest.home.favorites'), 'popular' => __('guest.home.popular'), 'new' => __('guest.home.new')] as $key => $title)
            @if (($highlights[$key] ?? []) !== [])
                <section class="mt-8" aria-label="{{ $title }}">
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-lg font-semibold text-ink-950">{{ $title }}</h2>
                        <a href="{{ $menuUrl }}" wire:navigate class="text-sm font-medium text-[var(--brand)]">{{ __('guest.home.see_menu') }}</a>
                    </div>
                    <ul class="-mx-4 mt-3 flex snap-x gap-3 overflow-x-auto px-4 pb-2 [scrollbar-width:none] sm:-mx-6 sm:px-6">
                        @foreach ($highlights[$key] as ['product' => $product, 'offer' => $offer])
                            <li class="w-40 shrink-0 snap-start">
                                <a href="{{ $menuUrl }}?item={{ $product->id }}" wire:navigate class="block overflow-hidden rounded-[1.35rem] bg-white shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5 transition active:scale-[0.98]">
                                    <div class="grid aspect-[4/3] place-items-center bg-ivory-100 text-4xl">
                                        @if ($image = $product->imageUrl(400))
                                            <img src="{{ $image }}" alt="" loading="lazy" class="size-full object-cover">
                                        @else
                                            <span aria-hidden="true">{{ $product->category?->icon ?: '🍽️' }}</span>
                                        @endif
                                    </div>
                                    <div class="p-3">
                                        <p class="line-clamp-1 text-sm font-semibold text-ink-950">{{ $product->name }}</p>
                                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-[var(--brand)]" dir="ltr">{{ Money::format($offer->priceMinor, $restaurant->currency) }}</p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach

        {{-- Branches --}}
        <section id="branches" class="mt-8 scroll-mt-24" aria-labelledby="branches-title"
                 x-data="{
                    here: null,
                    locating: false,
                    locate() {
                        if (! navigator.geolocation) return;
                        this.locating = true;
                        navigator.geolocation.getCurrentPosition(p => { this.here = [p.coords.latitude, p.coords.longitude]; this.locating = false }, () => this.locating = false, { timeout: 8000 });
                    },
                    km(lat, lng) {
                        if (! this.here || lat === null) return null;
                        const r = (d) => d * Math.PI / 180, [a, b] = this.here;
                        const h = Math.sin(r(lat - a) / 2) ** 2 + Math.cos(r(a)) * Math.cos(r(lat)) * Math.sin(r(lng - b) / 2) ** 2;
                        return 12742 * Math.asin(Math.sqrt(h));
                    },
                 }">
            <div class="flex items-center justify-between gap-3">
                <h2 id="branches-title" class="text-lg font-semibold text-ink-950">{{ __('guest.home.branches') }}</h2>
                <button type="button" x-on:click="locate()" x-show="! here" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-ink-700 ring-1 ring-ink-950/8">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                    <span x-show="! locating">{{ __('guest.home.near_me') }}</span>
                    <span x-show="locating" x-cloak>{{ __('guest.loading') }}</span>
                </button>
            </div>

            @if ($branches->isEmpty())
                <p class="mt-4 rounded-3xl bg-white p-6 text-center text-ink-500 ring-1 ring-ink-950/5">{{ __('public.restaurant.no_branches_hint') }}</p>
            @endif

            <ul class="mt-3 space-y-3">
                @foreach ($branches as $branch)
                    @php($state = $schedule->state($branch))
                    <li wire:key="branch-{{ $branch->id }}">
                        <div class="rounded-[1.5rem] bg-white p-4 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5 {{ $customer?->last_branch_id === $branch->id ? 'ring-2 !ring-[var(--brand)]' : '' }}">
                            <a href="{{ $links->branch($restaurant, $branch) }}" wire:navigate class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="flex items-center gap-2 font-semibold text-ink-950">
                                        {{ $branch->name }}
                                        @if ($customer?->last_branch_id === $branch->id)
                                            <span class="rounded-full bg-[var(--brand)] px-2 py-0.5 text-[0.625rem] font-semibold text-white">{{ __('guest.home.your_branch') }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 truncate text-sm text-ink-500">{{ $branch->city ?: $branch->address }}</p>
                                </div>
                                <span class="text-sm tabular-nums text-ink-500" x-show="km({{ $branch->latitude ?? 'null' }}, {{ $branch->longitude ?? 'null' }}) !== null" x-cloak
                                      x-text="km({{ $branch->latitude ?? 'null' }}, {{ $branch->longitude ?? 'null' }})?.toFixed(1) + ' ' + {{ Js::from(__('guest.home.km')) }}"></span>
                            </a>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <x-public.branch-status :branch="$branch" :schedule="$schedule" />
                                @if ($state->acceptsOrders())
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $busyTone[$busy[$branch->id]['level']] }}">{{ __('guest.busy.'.$busy[$branch->id]['level']) }} · {{ __('guest.home.wait', ['minutes' => $busy[$branch->id]['minutes']]) }}</span>
                                @endif
                            </div>
                            <div class="mt-3 flex gap-2">
                                <a href="{{ $links->branch($restaurant, $branch) }}" wire:navigate class="flex-1 rounded-full bg-[var(--brand)] py-2.5 text-center text-sm font-semibold text-white transition active:scale-[0.98]">
                                    {{ $state->acceptsOrders() ? __('guest.home.order_here') : __('guest.home.order_ahead') }}
                                </a>
                                @if ($phone = collect($branch->phones ?? [])->first())
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" aria-label="{{ __('guest.home.call') }}" class="grid size-10 place-items-center rounded-full bg-ivory-100 text-ink-700">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 0 0-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" stroke-linejoin="round"/></svg>
                                    </a>
                                @endif
                                @if ($branch->maps_url || $branch->hasLocation())
                                    <a href="{{ $branch->maps_url ?: 'https://www.google.com/maps/dir/?api=1&destination='.$branch->latitude.','.$branch->longitude }}" target="_blank" rel="noopener" aria-label="{{ __('public.branch.directions') }}" class="grid size-10 place-items-center rounded-full bg-ivory-100 text-ink-700">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </x-customer.shell>
</div>
