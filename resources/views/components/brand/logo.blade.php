@props(['subtitle' => null])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <x-brand.mark class="size-9 shrink-0 drop-shadow-sm" />
    <span class="flex flex-col leading-none">
        <span class="font-display text-[1.05rem] font-semibold tracking-tight" style="font-variation-settings: 'opsz' 32" dir="ltr">
            Drive-Thru<span class="text-brass-500">+</span>
        </span>
        @if ($subtitle)
            <span class="mt-1 text-[0.625rem] font-medium uppercase tracking-[0.18em] opacity-60 rtl:tracking-normal rtl:text-[0.6875rem]">{{ $subtitle }}</span>
        @endif
    </span>
</span>
