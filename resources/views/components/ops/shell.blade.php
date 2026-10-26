@props(['branch' => null, 'restaurant' => null, 'title', 'deviceId' => null, 'screen' => null, 'channel' => null])
@php
    use App\Domain\Ordering\Realtime\ChannelAccess;
    use App\Models\User;
    use App\Support\Locales;
    $restaurant = $branch?->restaurant ?? $restaurant;
    $user = auth()->user();
    $overview = $deviceId === null && $user instanceof User && app(ChannelAccess::class)->restaurantDashboard($user, $restaurant->id);
    $locale = app()->getLocale() === 'ar' ? 'ar-u-nu-latn' : 'en-GB';
    $screens = $deviceId === null && $branch !== null && $user instanceof User
        ? collect(\App\Http\Controllers\OpsController::screensFor($restaurant, \App\Domain\Ordering\Operations\Operator::staff($user, $restaurant->id)))->mapWithKeys(fn (string $key): array => [$key => __('ops.screens.'.$key)])->all()
        : [];
@endphp
<div x-data="opsShell({
        locale: @js($locale),
        timezone: @js($restaurant->timezone),
        heartbeatUrl: @js($deviceId ? route('screen.heartbeat') : null),
        pairUrl: @js(route('screen.pair')),
     })"
     data-live-channel="{{ $channel ?? 'branch.'.$branch->id.'.orders' }}"
     data-live-auth="{{ $deviceId ? route('realtime.auth') : url('/broadcasting/auth') }}"
     x-on:live-update.debounce.150ms="$wire.$refresh()"
     x-on:live-status="live = $event.detail.state"
     class="relative flex min-h-dvh flex-col">

    <header class="sticky top-0 z-30 flex items-center gap-4 border-b border-white/6 bg-ink-950/90 px-4 py-3 backdrop-blur-xl sm:px-6">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            @if ($logo = $branch?->logoUrl() ?? $restaurant->getFilamentAvatarUrl())
                <img src="{{ $logo }}" alt="" class="size-11 shrink-0 rounded-xl bg-white object-cover">
            @else
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white/8 font-semibold text-brass-200 ring-1 ring-white/10">{{ $restaurant->initials() }}</span>
            @endif
            <div class="min-w-0">
                <p class="truncate text-xs text-white/50">{{ $restaurant->name }}@if ($branch) · {{ $branch->name }}@endif</p>
                <h1 class="truncate text-xl font-semibold tracking-tight">{{ $title }}</h1>
            </div>
        </div>

        {{ $tools ?? '' }}

        <div class="flex shrink-0 items-center gap-2">
            <span class="hidden min-h-11 items-center gap-2 rounded-full px-3.5 text-sm font-medium ring-1 sm:inline-flex"
                  :class="{ 'bg-status-ready/12 text-sage-100 ring-status-ready/30': live === 'live', 'bg-status-delayed/15 text-ice-50 ring-status-delayed/40': live === 'offline', 'bg-white/6 text-white/60 ring-white/10': live === 'connecting' }"
                  role="status">
                <span class="size-2 rounded-full" :class="{ 'bg-status-ready': live === 'live', 'bg-status-delayed animate-pulse': live === 'offline', 'bg-white/40 animate-pulse': live === 'connecting' }"></span>
                <span x-show="live === 'live'">{{ __('ops.live.live') }}</span>
                <span x-show="live === 'offline'" x-cloak>{{ __('ops.live.offline') }}</span>
                <span x-show="live === 'connecting'" x-cloak>{{ __('ops.live.connecting') }}</span>
            </span>

            <span class="hidden min-w-[4.5rem] text-center text-2xl font-semibold tabular-nums text-white/90 md:block" dir="ltr" x-text="time"></span>

            <button type="button" x-on:click="enableSound()" class="grid size-14 place-items-center rounded-2xl ring-1 transition"
                    :class="sound ? 'bg-brass-400/15 text-brass-100 ring-brass-300/30' : 'bg-white/6 text-white/60 ring-white/10'"
                    :aria-pressed="sound" :aria-label="sound ? @js(__('ops.sound.on')) : @js(__('ops.sound.enable'))" :title="sound ? @js(__('ops.sound.on')) : @js(__('ops.sound.enable'))">
                <svg x-show="sound" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <svg x-show="! sound" x-cloak class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>

            <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                <button type="button" x-on:click="open = ! open" class="grid size-14 place-items-center rounded-2xl bg-white/6 text-white/70 ring-1 ring-white/10 transition hover:bg-white/10" aria-label="{{ __('ops.menu') }}" :aria-expanded="open">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                </button>
                <div x-show="open" x-cloak x-transition.origin.top x-on:click.outside="open = false"
                     class="absolute end-0 top-16 z-40 w-72 rounded-3xl bg-ink-900/95 p-2 shadow-2xl ring-1 ring-white/10 backdrop-blur-xl">
                    @foreach ($screens as $key => $label)
                        <a href="{{ route('ops.screen', [$restaurant, $branch->code, $key]) }}" @class(['flex min-h-14 items-center gap-3 rounded-2xl px-4 text-base', 'bg-white/10 text-white' => $screen === $key, 'text-white/80 hover:bg-white/8' => $screen !== $key])>{{ $label }}</a>
                    @endforeach
                    @if ($overview)
                        <a href="{{ route('ops.overview', $restaurant) }}" @class(['flex min-h-14 items-center gap-3 rounded-2xl px-4 text-base', 'bg-white/10 text-white' => $screen === 'overview', 'text-white/80 hover:bg-white/8' => $screen !== 'overview'])>{{ __('ops.screens.overview') }}</a>
                    @endif
                    @if ($screens !== [] || $overview)<div class="my-1 h-px bg-white/8"></div>@endif
                    <button type="button" x-on:click="fullscreen(); open = false" class="flex min-h-14 w-full items-center rounded-2xl px-4 text-start text-base text-white/80 hover:bg-white/8">{{ __('devices.screen.fullscreen') }}</button>
                    <a href="{{ route('locale.switch', Locales::other()) }}" lang="{{ Locales::other() }}" class="flex min-h-14 items-center rounded-2xl px-4 text-base text-white/80 hover:bg-white/8">{{ Locales::native(Locales::other()) }}</a>
                    @if ($deviceId)
                        <form method="POST" action="{{ route('screen.disconnect') }}" x-on:submit="if (! confirm(@js(__('devices.screen.disconnect_confirm')))) $event.preventDefault()">
                            @csrf
                            <button type="submit" class="flex min-h-14 w-full items-center rounded-2xl px-4 text-start text-base text-ice-100 hover:bg-status-delayed/15">{{ __('devices.screen.disconnect') }}</button>
                        </form>
                    @else
                        <a href="{{ url('/app/'.$restaurant->slug) }}" class="flex min-h-14 items-center rounded-2xl px-4 text-base text-white/80 hover:bg-white/8">{{ __('ops.back_to_panel') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <main id="main" class="relative flex-1">
        {{ $slot }}
    </main>

    {{-- Toasts --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition:enter="transition duration-300" x-transition:enter-start="translate-y-3 opacity-0"
                 class="pointer-events-auto flex max-w-xl items-center gap-3 rounded-2xl px-5 py-4 text-lg font-medium shadow-2xl ring-1"
                 :class="toast.tone === 'error' ? 'bg-ink-900 text-white ring-status-delayed/50' : 'bg-ink-800 text-white ring-white/10'">
                <span class="size-2.5 shrink-0 rounded-full" :class="toast.tone === 'error' ? 'bg-status-delayed' : 'bg-status-ready'"></span>
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
</div>
