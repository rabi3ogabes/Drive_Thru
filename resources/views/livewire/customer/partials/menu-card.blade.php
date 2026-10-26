@php
    use App\Domain\Menu\MenuLayout;
    use App\Support\Money;
    $layout ??= MenuLayout::for($restaurant);
    $product = $entry['product'];
    $offer = $entry['offer'];
    $search = mb_strtolower($product->getTranslation('name', 'en', false).' '.$product->getTranslation('name', 'ar', false));
    $image = $layout->imageStyle === 'none' ? null : $product->imageUrl($compact ? 400 : 800);
    $badges = $layout->showHighlights ? array_filter([
        $product->is_popular ? ['text' => __('public.menu.popular'), 'class' => 'bg-white/95 text-ink-900'] : null,
        $product->is_new ? ['text' => __('public.menu.new'), 'class' => 'bg-sage-100 text-teal-900'] : null,
        $entry['savings'] > 0 ? ['text' => __('public.menu.save', ['amount' => Money::format($entry['savings'], $currency)]), 'class' => 'bg-[var(--brand)] text-white'] : null,
    ]) : [];
@endphp
{{-- One dish: photo first, then name, a line about it and the price. --}}
<li x-show="matches($el)" data-search="{{ $search }}" wire:key="p-{{ $product->id }}" class="h-full">
    <button type="button" wire:click="openProduct({{ $product->id }})"
            @class([
                'group relative flex h-full w-full overflow-hidden bg-white text-start ring-1 ring-ink-950/5 transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-0.5 hover:shadow-[0_22px_40px_-30px_color-mix(in_oklab,var(--color-ink-950)_55%,transparent)] active:scale-[0.985]',
                'flex-col rounded-[1.5rem] shadow-[var(--shadow-hairline)]' => ! $compact,
                'items-stretch rounded-[1.5rem] shadow-[var(--shadow-hairline)]' => $compact,
                'opacity-60' => ! $offer->isAvailable(),
            ])>
        @if ($layout->imageStyle !== 'none')
            <span @class([
                'relative block shrink-0 overflow-hidden bg-[color-mix(in_oklab,var(--brand)_8%,var(--color-ivory-100))]',
                'm-1.5 aspect-[4/3] rounded-[1.15rem]' => ! $compact,
                'm-2 size-28 rounded-[1.15rem] sm:size-32' => $compact,
            ])>
                @if ($image)
                    <img src="{{ $image }}" alt="" loading="lazy" decoding="async"
                         class="absolute inset-0 size-full object-cover transition duration-700 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:scale-[1.05]">
                @else
                    <span class="absolute inset-0 grid place-items-center text-3xl" aria-hidden="true">{{ $category->icon ?: '🍽️' }}</span>
                @endif
                @if ($badges !== [] && ! $compact)
                    <span class="absolute start-2 top-2 flex flex-wrap gap-1">
                        @foreach ($badges as $badge)
                            <span class="rounded-full px-2.5 py-1 text-[0.625rem] font-semibold shadow-sm {{ $badge['class'] }}">{{ $badge['text'] }}</span>
                        @endforeach
                    </span>
                @endif
                @unless ($offer->isAvailable())
                    <span class="absolute inset-x-2 bottom-2 rounded-full bg-ink-950/75 px-2.5 py-1 text-center text-[0.6875rem] font-semibold text-white backdrop-blur">{{ $offer->unavailable->getLabel() }}</span>
                @endunless
            </span>
        @endif

        <span @class(['flex min-w-0 flex-1 flex-col', 'px-3.5 pb-3.5 pt-2.5' => ! $compact, 'py-3.5 pe-4 ps-2' => $compact])>
            @if ($badges !== [] && $compact)
                <span class="mb-1 flex flex-wrap gap-1">
                    @foreach ($badges as $badge)
                        <span class="rounded-full px-2 py-0.5 text-[0.625rem] font-semibold ring-1 ring-ink-950/5 {{ $badge['class'] }}">{{ $badge['text'] }}</span>
                    @endforeach
                </span>
            @endif
            <span class="line-clamp-2 text-[0.9375rem] font-semibold leading-snug text-ink-950">{{ $product->name }}</span>
            @if ($layout->showDescriptions && $product->description)
                <span @class(['mt-1 text-[0.8125rem] leading-relaxed text-ink-500', 'line-clamp-2' => $compact, 'line-clamp-2 max-sm:line-clamp-1' => ! $compact])>{{ $product->description }}</span>
            @endif
            <span class="mt-auto flex items-center justify-between gap-2 pt-3">
                <span class="flex flex-col tabular-nums leading-tight" dir="ltr">
                    @if ($offer->isDiscounted())<span class="text-[0.6875rem] text-ink-400 line-through">{{ Money::format($offer->basePriceMinor, $currency) }}</span>@endif
                    <span class="text-[0.9375rem] font-semibold text-ink-950">{{ Money::format($offer->priceMinor, $currency) }}</span>
                </span>
                @if ($offer->isAvailable() && $ordering)
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[var(--brand)] text-white shadow-[0_8px_16px_-8px_var(--brand)] transition duration-300 group-hover:scale-105" aria-hidden="true">
                        <svg wire:loading.remove wire:target="openProduct({{ $product->id }})" class="size-4" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        <svg wire:loading wire:target="openProduct({{ $product->id }})" class="size-4 animate-spin" viewBox="0 0 16 16" fill="none"><path d="M8 2a6 6 0 1 0 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </span>
                @elseif (! $offer->isAvailable() && $layout->imageStyle === 'none')
                    <span class="text-xs font-semibold text-ink-500">{{ $offer->unavailable->getLabel() }}</span>
                @endif
            </span>
        </span>
    </button>
</li>
