<div class="space-y-3">
@foreach ($items as $i => $item)
    <details class="group rounded-[1.5rem] bg-white/70 ring-1 ring-ink-950/5 transition open:bg-white open:shadow-[var(--shadow-soft)] [&_summary::-webkit-details-marker]:hidden" data-reveal style="--i:{{ $i }}">
        <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-6 text-start font-semibold text-ink-950">
            {{ $item['q'] }}
            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-ivory-100 text-ink-700 transition duration-300 group-open:rotate-45"><svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
        </summary>
        <p class="px-6 pb-6 leading-relaxed text-ink-600">{{ str_replace(':days', (string) $demoDays, $item['a']) }}</p>
    </details>
@endforeach
</div>
