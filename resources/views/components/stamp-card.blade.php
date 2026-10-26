@props([
    'name',
    'needed',
    'filled' => 0,
    'target' => null,
    'reward' => null,
    'accent' => null,
    'animate' => false,
])
@php
    $needed = max(2, min(20, (int) $needed));
    $filled = max(0, min($needed, (int) $filled));
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $accent) === 1 ? $accent : null;
    $columns = $needed <= 6 ? $needed : (int) ceil($needed / 2);
@endphp
{{-- A guest's stamp card: their restaurant's colour, one circle per stamp, the reward at the end. --}}
<div {{ $attributes->class('theme-fixed relative overflow-hidden rounded-[1.75rem] p-1.5 ring-1 ring-black/5') }}
     style="--card: {{ $accent ?? 'var(--brand, var(--color-teal-800))' }}; background: color-mix(in oklab, var(--card) 14%, white);">
    <div class="relative overflow-hidden rounded-[calc(1.75rem-0.375rem)] p-5 text-white shadow-[inset_0_1px_0_color-mix(in_oklab,white_18%,transparent)]"
         style="background: linear-gradient(140deg, color-mix(in oklab, var(--card) 88%, white) 0%, var(--card) 55%, color-mix(in oklab, var(--card) 80%, var(--color-ink-950)) 100%);">
        <div aria-hidden="true" class="pointer-events-none absolute -end-14 -top-16 size-44 rounded-full bg-white/10 blur-2xl"></div>

        <div class="relative flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-lg font-semibold tracking-tight">{{ $name }}</p>
                @if ($target || $reward)
                    <p class="mt-0.5 text-sm text-white/75">
                        {{ __('loyalty.stamps.card_line', ['count' => $needed, 'item' => $target ?? '…', 'reward' => $reward ?? '…']) }}
                    </p>
                @endif
            </div>
            <span class="shrink-0 rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold tabular-nums ring-1 ring-white/20" dir="ltr">{{ $filled }}/{{ $needed }}</span>
        </div>

        <ol class="relative mt-5 grid gap-2.5" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr));" aria-label="{{ __('loyalty.stamps.progress', ['filled' => $filled, 'needed' => $needed]) }}">
            @for ($i = 1; $i <= $needed; $i++)
                @php($done = $i <= $filled)
                <li @class([
                    'grid aspect-square place-items-center rounded-full transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)]',
                    'bg-white text-[color:var(--card)] shadow-[0_6px_14px_-6px_color-mix(in_oklab,var(--color-ink-950)_35%,transparent)]' => $done,
                    'border-[1.5px] border-dashed border-white/45' => ! $done && $i < $needed,
                    'bg-white/12 ring-[1.5px] ring-white/50' => ! $done && $i === $needed,
                    'motion-safe:animate-[stamp-in_520ms_cubic-bezier(0.32,0.72,0,1)_both]' => $done && $animate,
                ]) @if ($done && $animate) style="animation-delay: {{ ($i - 1) * 60 }}ms" @endif>
                    @if ($i === $needed)
                        <svg class="size-[45%]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 12v8H4v-8M2 7h20v5H2zM12 20V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Zm0 0h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @elseif ($done)
                        <svg class="size-[46%]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @endif
                </li>
            @endfor
        </ol>
    </div>
</div>
@once
    <style>
        @keyframes stamp-in { from { transform: scale(0.4) rotate(-18deg); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
@endonce
