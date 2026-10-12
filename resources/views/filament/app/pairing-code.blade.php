@php
    use App\Domain\Branches\Actions\IssuePairingCode;
    use App\Domain\Branches\Links;
@endphp
<div class="space-y-6">
    @if ($device)
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            {{ $device->name }} · {{ $device->kind->getLabel() }} · {{ $device->branch->name }}
        </p>
    @endif

    <div class="rounded-3xl bg-gray-950 px-6 py-8 text-center shadow-inner dark:bg-white/5"
         x-data="{ copied: false }">
        <p class="text-xs font-medium uppercase tracking-[0.2em] text-white/50 rtl:tracking-normal">{{ __('devices.code.label') }}</p>
        <p class="mt-3 text-5xl font-semibold tabular-nums tracking-[0.18em] text-white sm:text-6xl" dir="ltr" aria-live="polite">{{ $code }}</p>
        <p class="mt-3 text-sm text-white/50">{{ trans_choice('devices.code.expires', IssuePairingCode::VALID_MINUTES, ['count' => IssuePairingCode::VALID_MINUTES]) }}</p>
        <button type="button"
                x-on:click="navigator.clipboard?.writeText(@js(str_replace(' ', '', $code))); copied = true; setTimeout(() => copied = false, 1800)"
                class="mt-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/15 transition hover:bg-white/15">
            <x-filament::icon icon="heroicon-m-clipboard-document" class="size-4" />
            <span x-show="! copied">{{ __('devices.code.copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('devices.code.copied') }}</span>
        </button>
    </div>

    <div class="grid grid-cols-[1fr_auto] items-center gap-5">
        <ol class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
            <li class="flex gap-3">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">1</span>
                <span>{!! __('devices.code.step_1', ['url' => '<span class="font-semibold text-gray-950 dark:text-white" dir="ltr">'.e(Links::display($pairUrl)).'</span>']) !!}</span>
            </li>
            <li class="flex gap-3">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">2</span>
                <span>{{ __('devices.code.step_2') }}</span>
            </li>
            <li class="flex gap-3">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">3</span>
                <span>{{ __('devices.code.step_3') }}</span>
            </li>
        </ol>
        <div class="w-28 rounded-2xl bg-white p-2 ring-1 ring-gray-950/5 [&_svg]:h-auto [&_svg]:w-full" aria-label="{{ __('devices.code.qr') }}">
            {!! $qr !!}
        </div>
    </div>
</div>
