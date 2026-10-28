@php
    use App\Filament\App\Resources\Customers\CustomerResource;
    use App\Support\Digits;
@endphp
{{-- How many guests the chosen groups reach right now, with a few of them (phones masked). --}}
<div @class([
    'flex flex-col gap-3 rounded-2xl p-4 sm:flex-row sm:items-center sm:justify-between',
    'bg-primary-50 dark:bg-primary-500/10' => $count > 0,
    'bg-warning-50 dark:bg-warning-500/10' => $count === 0,
])>
    <div class="min-w-0">
        <p @class(['text-base font-semibold', 'text-primary-800 dark:text-primary-200' => $count > 0, 'text-warning-800 dark:text-warning-200' => $count === 0])>
            {{ trans_choice('campaigns.admin.audience.count', $count, ['count' => Digits::number($count)]) }}
        </p>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('campaigns.admin.audience.note') }}</p>
    </div>
    @if ($sample->isNotEmpty())
        <div class="shrink-0">
            <p class="text-[0.6875rem] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('campaigns.admin.audience.sample') }}</p>
            <ul class="mt-1 grid gap-0.5 text-sm text-gray-700 dark:text-gray-200">
                @foreach ($sample as $guest)
                    <li class="truncate">{{ CustomerResource::displayName($guest) }} <span class="text-gray-400 dark:text-gray-500"><bdi dir="ltr">{{ $guest->maskedPhone() }}</bdi></span></li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
