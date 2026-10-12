@props(['class' => 'size-8'])
{{-- Drive-Thru Plus mark: a road curve passing a pickup window, closing into a "+" --}}
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 40 40" fill="none" aria-hidden="true">
    <rect x="1" y="1" width="38" height="38" rx="12" fill="url(#dtp-mark-bg)"/>
    <rect x="1.5" y="1.5" width="37" height="37" rx="11.5" stroke="white" stroke-opacity=".12"/>
    <path d="M9 27.5c4.5 0 6.2-3 8-6.6 1.9-3.8 3.8-7.4 9-7.4h5" stroke="#D5B582" stroke-width="2.6" stroke-linecap="round"/>
    <path d="M27.5 22.5v7M24 26h7" stroke="white" stroke-width="2.6" stroke-linecap="round"/>
    <circle cx="11" cy="13" r="2.2" fill="white" fill-opacity=".9"/>
    <defs>
        <linearGradient id="dtp-mark-bg" x1="4" y1="2" x2="36" y2="40" gradientUnits="userSpaceOnUse">
            <stop stop-color="#1E5D57"/>
            <stop offset="1" stop-color="#082423"/>
        </linearGradient>
    </defs>
</svg>
