{{-- A small rendering of a template: its background, brand bar and colours. --}}
@php
    $colors = $getRecord()->spec['colors'] ?? [];
@endphp
<span class="flex h-12 w-20 flex-col justify-between rounded-lg p-1.5 ring-1 ring-gray-950/10 dark:ring-white/15" style="background: {{ $colors['background'] ?? '#ffffff' }}" aria-hidden="true">
    <span class="h-1.5 w-8 rounded-full" style="background: {{ $colors['brand'] ?? '#000000' }}"></span>
    <span class="flex gap-1">
        @foreach (['brand', 'accent', 'text'] as $key)
            <span class="size-2.5 rounded-full" style="background: {{ $colors[$key] ?? '#000000' }}"></span>
        @endforeach
    </span>
</span>
