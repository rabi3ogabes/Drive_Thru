@props(['subtitle' => null, 'animated' => false])
{{-- The Dhawq wordmark: the mark, then ذوق and DHAWQ; the page's language leads. --}}
@php
    $arabicFirst = app()->getLocale() === 'ar';
    $arabic = '<span class="text-[1.2rem] font-bold leading-none" style="font-family: var(--font-display-ar)" lang="ar" dir="rtl">'.e(\App\Support\Brand::ARABIC).'</span>';
    $latin = '<span class="font-display text-[0.8rem] font-semibold uppercase leading-none tracking-[0.18em]" style="font-variation-settings: \'opsz\' 32" lang="en" dir="ltr">'.e(\Illuminate\Support\Str::upper(\App\Support\Brand::LATIN)).'</span>';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <x-brand.mark class="size-9 shrink-0 drop-shadow-sm" :animated="$animated" />
    <span class="flex flex-col leading-none">
        <span class="flex items-center gap-2">
            {!! $arabicFirst ? $arabic : $latin !!}
            <span class="hidden h-3 w-px bg-current opacity-20 lg:block" aria-hidden="true"></span>
            <span class="hidden opacity-70 lg:inline">{!! $arabicFirst ? $latin : $arabic !!}</span>
        </span>
        @if ($subtitle)
            <span class="mt-1.5 text-[0.625rem] font-medium uppercase tracking-[0.18em] opacity-60 rtl:tracking-normal rtl:text-[0.6875rem]">{{ $subtitle }}</span>
        @endif
    </span>
</span>
