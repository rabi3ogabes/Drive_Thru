@php
    use App\Support\Locales;
    $next = Locales::other();
@endphp
<x-layouts.site :title="__('devices.pair.title').' · '.config('app.name')" theme-color="#0a0f1c" color-scheme="dark" body-class="bg-ink-950 text-white">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -top-48 start-1/2 size-[44rem] -translate-x-1/2 rounded-full bg-teal-700/20 blur-3xl rtl:translate-x-1/2"></div>
        <div class="absolute -bottom-56 end-[-12rem] size-[30rem] rounded-full bg-brass-500/10 blur-3xl"></div>
    </div>

    <div class="fixed inset-x-0 top-0 z-20 flex items-center justify-between p-5 sm:p-8">
        <span class="text-white/90"><x-brand.mark class="size-9" /></span>
        <a href="{{ route('locale.switch', $next) }}" lang="{{ $next }}" class="rounded-full bg-white/8 px-4 py-2 text-sm font-medium text-white/80 ring-1 ring-white/10 transition hover:bg-white/12 hover:text-white">
            {{ Locales::native($next) }}
        </a>
    </div>

    <main id="main" class="relative z-10 grid min-h-dvh place-items-center px-5 py-24">
        <div class="reveal w-full max-w-lg text-center">
            <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-white/6 ring-1 ring-white/10">
                <svg class="size-8 text-brass-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4" stroke-linecap="round"/>
                </svg>
            </span>

            <h1 class="display mt-8 text-4xl text-white sm:text-5xl">{{ __('devices.pair.heading') }}</h1>
            <p class="mx-auto mt-4 max-w-sm text-base leading-relaxed text-white/60">{{ __('devices.pair.lead') }}</p>

            @if ($status === 'disconnected')
                <p role="status" class="mx-auto mt-6 flex w-fit items-center gap-2 rounded-full bg-brass-500/12 px-4 py-2 text-sm text-brass-200 ring-1 ring-brass-400/20">
                    <span class="size-1.5 rounded-full bg-brass-300"></span>
                    {{ __('devices.pair.disconnected') }}
                </p>
            @endif

            <form method="POST" action="{{ route('screen.pair.store') }}" class="mt-10"
                  x-data="{ code: @js(old('code', '')), busy: false, get digits() { return this.code.replace(/\D/g, '').slice(0, 6) } }"
                  x-on:submit="busy = true">
                @csrf
                <label for="code" class="sr-only">{{ __('devices.pair.code') }}</label>
                <input id="code" name="code" x-model="code" required autofocus
                       inputmode="numeric" autocomplete="one-time-code" maxlength="7" dir="ltr"
                       x-on:input="if (digits.length === 6 && ! busy) { $nextTick(() => { busy = true; $el.form.submit() }) }"
                       placeholder="000 000"
                       aria-describedby="code-hint @error('code') code-error @enderror"
                       class="block w-full rounded-3xl border-0 bg-white/6 px-6 py-6 text-center font-medium text-5xl tracking-[0.35em] text-white tabular-nums ring-1 ring-white/12 placeholder:text-white/15 focus:bg-white/8 focus:ring-2 focus:ring-brass-400 sm:text-6xl @error('code') ring-2 ring-status-delayed @enderror">
                <p id="code-hint" class="mt-4 text-sm text-white/45">{{ __('devices.pair.hint') }}</p>
                @error('code')
                    <p id="code-error" role="alert" class="mt-3 text-sm font-medium text-ice-100">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn btn-primary mx-auto mt-8 min-h-14 !bg-brass-500 text-lg !text-ink-950 hover:!bg-brass-400 disabled:opacity-60" x-bind:disabled="busy || digits.length !== 6">
                    <span x-show="! busy">{{ __('devices.pair.submit') }}</span>
                    <span x-show="busy" x-cloak>{{ __('devices.pair.pairing') }}</span>
                    <span class="btn-icon !bg-ink-950/10 !text-ink-950" aria-hidden="true">
                        <svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </button>
            </form>
        </div>
    </main>
</x-layouts.site>
