@php
    $restaurant = $this->restaurant;
    $tone = [
        'info' => 'bg-ice-50 text-ink-800 ring-ice-300/60 dark:bg-ice-400/10 dark:text-ice-100 dark:ring-ice-400/20',
        'success' => 'bg-teal-50 text-teal-900 ring-teal-600/15 dark:bg-teal-400/10 dark:text-teal-100 dark:ring-teal-400/20',
        'warning' => 'bg-brass-50 text-brass-900 ring-brass-500/25 dark:bg-brass-400/10 dark:text-brass-100 dark:ring-brass-400/20',
        'critical' => 'bg-red-50 text-red-900 ring-red-600/20 dark:bg-red-500/10 dark:text-red-100 dark:ring-red-400/20',
    ];
@endphp
@php($banner = $this->banner)
@php($announcements = $this->announcements)
@php($suspended = $restaurant !== null && ! $restaurant->isOperational())
<div @class(['space-y-3 pt-6 sm:pt-8' => $suspended || $banner || $announcements->isNotEmpty()])>
    @if ($suspended)
        <div role="alert" class="flex items-start gap-3 rounded-2xl px-4 py-3.5 text-sm ring-1 {{ $tone['critical'] }}">
            <x-filament::icon icon="heroicon-o-pause-circle" class="mt-0.5 size-5 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ __('clients.notices.suspended.title') }}</p>
                <p class="mt-0.5 opacity-80">{{ __('clients.notices.suspended.body') }}</p>
            </div>
        </div>
    @endif

    @if ($banner)
        <div role="status" class="flex items-start gap-3 rounded-2xl px-4 py-3.5 text-sm ring-1 {{ $tone[$banner['level']] ?? $tone['warning'] }}">
            <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="mt-0.5 size-5 shrink-0" />
            <p class="min-w-0 flex-1">{{ $banner['message'] }}</p>
        </div>
    @endif

    @foreach ($announcements as $announcement)
        <div wire:key="announcement-{{ $announcement->id }}" class="flex items-start gap-3 rounded-2xl px-4 py-3.5 text-sm ring-1 {{ $tone[$announcement->level->value] ?? $tone['info'] }}">
            <x-filament::icon :icon="$announcement->level->getIcon()" class="mt-0.5 size-5 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $announcement->title }}</p>
                @if ($announcement->body)
                    <p class="mt-0.5 opacity-80">{{ $announcement->body }}</p>
                @endif
                @if ($announcement->action_url && $announcement->action_label)
                    <a href="{{ $announcement->action_url }}" target="_blank" rel="noopener" class="mt-1.5 inline-flex items-center gap-1 font-medium underline decoration-current/30 underline-offset-4 hover:decoration-current">
                        {{ $announcement->action_label }}
                    </a>
                @endif
            </div>
            <button type="button" wire:click="dismiss({{ $announcement->id }})" class="-m-1 rounded-full p-1 opacity-60 transition hover:opacity-100" aria-label="{{ __('clients.notices.dismiss') }}">
                <x-filament::icon icon="heroicon-m-x-mark" class="size-4" />
            </button>
        </div>
    @endforeach
</div>
