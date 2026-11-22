@php
    $count = array_sum(array_column($ticket['items'], 'quantity'));
    $minutes = intdiv((int) $ticket['age_seconds'], 60);
    $label = __('ops.pass.ticket_label', ['number' => $ticket['number'], 'items' => trans_choice('ops.pass.items', $count, ['count' => $count]), 'minutes' => $minutes]).($ticket['late'] ? ', '.__('ops.pass.late_word') : '');
@endphp
<li class="pass-slot" wire:key="ticket-{{ $kind }}-{{ $ticket['order_id'] }}">
    <button type="button" wire:click="select({{ $ticket['order_id'] }})"
            @class(['pass-ticket', 'is-late' => $ticket['late'], 'is-selected' => $selected === $ticket['order_id']])
            :class="{ 'is-late': late(@js($ticket['target_at'])) }"
            aria-label="{{ $label }}" aria-pressed="{{ $selected === $ticket['order_id'] ? 'true' : 'false' }}"
            data-pass-ticket>
        <span class="pass-ticket-head">
            <span class="pass-ticket-number" dir="ltr">{{ $ticket['number'] }}</span>
            @if ($ticket['arrived'])
                <span class="pass-ticket-car" title="{{ __('ops.pass.car_here') }}">
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 13.5h12M5.5 13.5V16m9-2.5V16M3.5 13.5l1.4-4.6A2 2 0 0 1 6.8 7.5h6.4a2 2 0 0 1 1.9 1.4l1.4 4.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="6.5" cy="11.5" r=".6" fill="currentColor"/><circle cx="13.5" cy="11.5" r=".6" fill="currentColor"/></svg>
                </span>
            @endif
        </span>
        <span class="pass-ticket-status">{{ __('ops.pass.'.$ticket['status']) }}</span>
        <span class="pass-ticket-items">
            @foreach (array_slice($ticket['items'], 0, 5) as $item)
                <span class="pass-ticket-item">
                    <span dir="auto"><span dir="ltr">{{ $item['quantity'] }}</span> {{ $item['name'] }}</span>
                    @foreach ($item['options'] as $option)
                        <span class="pass-ticket-option" dir="auto">{{ $option }}</span>
                    @endforeach
                    @if ($item['notes'])<span class="pass-ticket-note" dir="auto">{{ $item['notes'] }}</span>@endif
                </span>
            @endforeach
            @if (count($ticket['items']) > 5)
                <span class="pass-ticket-option">+{{ count($ticket['items']) - 5 }}</span>
            @endif
        </span>
        <span class="pass-ticket-foot">
            <span class="pass-ticket-timer" dir="ltr" x-text="age(@js($ticket['received_at']))">{{ sprintf('%d:%02d', $minutes, (int) $ticket['age_seconds'] % 60) }}</span>
            @if ($ticket['car'])<span class="pass-ticket-meta" dir="auto">{{ $ticket['car'] }}</span>@elseif ($ticket['lane'])<span class="pass-ticket-meta" dir="auto">{{ $ticket['lane'] }}</span>@endif
        </span>
    </button>
</li>
