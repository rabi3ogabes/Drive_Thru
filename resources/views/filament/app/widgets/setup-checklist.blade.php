@php
    $radius = 34;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference * (1 - $progress / 100);
    $next = collect($steps)->firstWhere('done', false);
@endphp
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="@container"><div class="flex flex-col gap-6 @4xl:flex-row @4xl:items-center">
            <div class="flex items-center gap-5 @4xl:w-72 @4xl:shrink-0">
                <div class="relative size-20 shrink-0" role="img" aria-label="{{ __('setup.progress', ['percent' => $progress]) }}">
                    <svg class="size-20 -rotate-90" viewBox="0 0 80 80" aria-hidden="true">
                        <circle cx="40" cy="40" r="{{ $radius }}" fill="none" stroke-width="7" class="stroke-gray-100 dark:stroke-white/10" />
                        <circle cx="40" cy="40" r="{{ $radius }}" fill="none" stroke-width="7" stroke-linecap="round"
                                class="stroke-primary-600 transition-[stroke-dashoffset] duration-700 ease-out motion-reduce:transition-none dark:stroke-primary-400"
                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}" />
                    </svg>
                    <span class="absolute inset-0 grid place-items-center text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $progress }}%</span>
                </div>
                <div class="min-w-0">
                    <p class="font-serif text-xl font-medium tracking-tight text-gray-950 dark:text-white">
                        {{ $complete ? __('setup.done_title') : __('setup.title') }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $complete ? __('setup.done_body') : __('setup.body') }}
                    </p>
                    @if ($complete)
                        <x-filament::link tag="button" wire:click="dismiss" size="sm" class="mt-2">{{ __('setup.dismiss') }}</x-filament::link>
                    @endif
                </div>
            </div>

            <ol class="grid flex-1 gap-2 @md:grid-cols-2 @3xl:grid-cols-3">
                @foreach ($steps as $step)
                    <li>
                        <a href="{{ $step['url'] }}" wire:navigate @class([
                            'group flex h-full items-start gap-3 rounded-2xl p-3.5 ring-1 transition',
                            'bg-gray-50 ring-gray-950/5 dark:bg-white/5 dark:ring-white/5' => $step['done'],
                            'bg-white ring-gray-950/10 hover:ring-primary-600/40 hover:shadow-sm dark:bg-white/5 dark:ring-white/10 dark:hover:ring-primary-400/40' => ! $step['done'],
                            'ring-2 ring-primary-600/50 dark:ring-primary-400/50' => $next && $next['key'] === $step['key'],
                        ])>
                            @if ($step['done'])
                                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-success-600 text-white dark:bg-success-500">
                                    <x-filament::icon icon="heroicon-m-check" class="size-4" />
                                </span>
                            @else
                                <span class="grid size-6 shrink-0 place-items-center rounded-full text-xs font-semibold text-gray-500 ring-1 ring-gray-950/15 dark:text-gray-400 dark:ring-white/20">{{ $loop->iteration }}</span>
                            @endif
                            <span class="min-w-0">
                                <span @class(['block text-sm font-medium', 'text-gray-500 line-through decoration-gray-300 dark:text-gray-500' => $step['done'], 'text-gray-950 dark:text-white' => ! $step['done']])>
                                    {{ __("setup.steps.{$step['key']}.title") }}
                                </span>
                                @unless ($step['done'])
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ __("setup.steps.{$step['key']}.hint") }}</span>
                                @endunless
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div></div>
    </x-filament::section>
</x-filament-widgets::widget>
