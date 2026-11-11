{{-- Ops screens need a branch: shown instead of a 403 when the restaurant has none, or the person isn't on one. --}}
@php
    $arrow = '<svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
@endphp
<x-layouts.error
    :title="$addUrl ? __('ops.no_branch.title') : __('ops.no_branch.unassigned_title')"
    :message="$addUrl ? __('ops.no_branch.owner') : __('ops.no_branch.staff')">
    <x-slot:actions>
        @if ($addUrl)
            <a href="{{ $addUrl }}" class="btn btn-primary">
                <span>{{ __('ops.no_branch.add') }}</span>
                <span class="btn-icon" aria-hidden="true">{!! $arrow !!}</span>
            </a>
        @endif
        <a href="{{ $dashboardUrl }}" @class(['btn', 'btn-ghost' => $addUrl, 'btn-primary' => ! $addUrl])>
            <span>{{ __('ops.no_branch.dashboard') }}</span>
            <span class="btn-icon" aria-hidden="true">{!! $arrow !!}</span>
        </a>
    </x-slot:actions>
</x-layouts.error>
