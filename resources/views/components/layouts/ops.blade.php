@props(['title'])
{{-- Operations screens: dark, calm, readable from 3 m, never dependent on hover. --}}
<x-layouts.site :title="$title" theme-color="#0a0f1c" color-scheme="dark" body-class="bg-ink-950 text-white antialiased [-webkit-tap-highlight-color:transparent]">
    <x-slot:head>
        <meta name="robots" content="noindex">
        @vite('resources/js/live.js')
    </x-slot:head>

    {{ $slot }}

    @include('ops.partials.script')
</x-layouts.site>
