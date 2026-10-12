@props(['restaurant', 'branch' => null])
@php
    $logo = $branch?->logoUrl() ?? $restaurant->getFilamentAvatarUrl();
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $restaurant->accent_color) === 1 ? $restaurant->accent_color : null;
@endphp
@if ($logo)
    <img src="{{ $logo }}" alt="{{ $restaurant->name }}" {{ $attributes->class(['shrink-0 rounded-2xl bg-white object-cover shadow-[var(--shadow-lift)] ring-4 ring-white']) }}>
@else
    <span aria-hidden="true" {{ $attributes->class(['grid shrink-0 place-items-center @container rounded-2xl bg-teal-900 font-semibold text-brass-100 shadow-[var(--shadow-lift)] ring-4 ring-white']) }}
          @if ($accent) style="background-color: {{ $accent }}" @endif>
        <span class="text-[36cqw] leading-none">{{ $restaurant->initials() }}</span>
    </span>
@endif
