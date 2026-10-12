<x-filament-panels::page>
    @if (! $this->usesDatabaseSessions())
        <x-filament::section icon="heroicon-o-information-circle">
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('security.sessions.driver_notice') }}</p>
        </x-filament::section>
    @else
        @php($sessions = $this->getSessions())

        <x-filament::section :heading="__('security.sessions.list_heading')" :description="trans_choice('security.sessions.count', $sessions->count(), ['count' => $sessions->count()])">
            <ul role="list" class="-my-2 divide-y divide-gray-950/5 dark:divide-white/5">
                @foreach ($sessions as $session)
                    <li class="flex items-center gap-4 py-4" wire:key="session-{{ $session->id }}">
                        <span @class([
                            'grid size-11 shrink-0 place-items-center rounded-2xl',
                            'bg-primary-50 text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-300' => $session->current,
                            'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => ! $session->current,
                        ])>
                            <x-filament::icon
                                :icon="$session->mobile ? 'heroicon-o-device-phone-mobile' : 'heroicon-o-computer-desktop'"
                                class="size-5"
                            />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                                <span>{{ $session->browser }} · {{ $session->platform }}</span>
                                @if ($session->current)
                                    <x-filament::badge color="success" size="sm" icon="heroicon-m-check-circle">
                                        {{ __('security.sessions.this_device') }}
                                    </x-filament::badge>
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <span dir="ltr">{{ $session->ip ?? '—' }}</span>
                                <span aria-hidden="true">·</span>
                                <time datetime="{{ $session->lastActive->toIso8601String() }}" title="{{ $session->lastActive->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}">
                                    {{ $session->current ? __('security.sessions.active_now') : __('security.sessions.last_active', ['time' => $session->lastActive->diffForHumans()]) }}
                                </time>
                            </p>
                        </div>

                        @unless ($session->current)
                            {{ ($this->signOutSessionAction)(['session' => $session->id]) }}
                        @endunless
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
