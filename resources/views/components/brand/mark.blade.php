@props(['class' => 'size-8', 'animated' => false, 'monochrome' => false])
{{-- The Dhawq mark: the letter ذ in brass on a deep-teal tile; its dot is the "order ready" light. Master drawing: resources/brand/dhawq-mark.svg --}}
@php
    $id = 'dhawq-mark-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $ink = $monochrome ? 'currentColor' : '#D5B582';
@endphp
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 40 40" fill="none" aria-hidden="true">
    @unless ($monochrome)
        <rect x="1" y="1" width="38" height="38" rx="12" fill="url(#{{ $id }})"/>
        <rect x="1.5" y="1.5" width="37" height="37" rx="11.5" fill="none" stroke="white" stroke-opacity=".12"/>
    @endunless
    <path d="M14 16c6 .6 11 3.6 12.2 8.4.7 2.9-1.3 5.4-4.6 5.4H12.5" fill="none" stroke="{{ $ink }}" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="20" cy="10" r="2.8" fill="{{ $ink }}" @if ($animated) class="brand-dot-glow" @endif/>
    @unless ($monochrome)
        <defs>
            <linearGradient id="{{ $id }}" x1="4" y1="2" x2="36" y2="40" gradientUnits="userSpaceOnUse">
                <stop stop-color="#1E5D57"/>
                <stop offset="1" stop-color="#082423"/>
            </linearGradient>
        </defs>
    @endunless
</svg>
