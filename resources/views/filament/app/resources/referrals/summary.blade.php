@php
    $joined = (int) ($counts['pending'] ?? 0) + (int) ($counts['rewarded'] ?? 0);
    $tiles = [
        'joined' => $joined,
        'rewarded' => (int) ($counts['rewarded'] ?? 0),
        'pending' => (int) ($counts['pending'] ?? 0),
        'rejected' => (int) ($counts['rejected'] ?? 0),
    ];
@endphp
<div class="space-y-4">
    @unless ($live)
        <div class="flex items-center gap-3 rounded-xl bg-warning-50 px-4 py-3 text-sm text-warning-800 ring-1 ring-warning-600/15 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-400/20">
            <x-filament::icon icon="heroicon-m-pause-circle" class="size-5 shrink-0" />
            <span>{{ __('referrals.off_notice') }}</span>
        </div>
    @endunless

    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($tiles as $key => $value)
            <div class="rounded-xl bg-white px-4 py-3.5 shadow-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('referrals.stats.'.$key) }}</dt>
                <dd @class([
                    'mt-1 text-2xl font-semibold tabular-nums tracking-tight',
                    'text-success-600 dark:text-success-400' => $key === 'rewarded' && $value > 0,
                    'text-gray-950 dark:text-white' => ! ($key === 'rewarded' && $value > 0),
                ])>{{ number_format($value) }}</dd>
            </div>
        @endforeach
    </dl>
</div>
