@php
    use App\Support\Locales;
    $next = Locales::other();
    $places = $isPlatform ? collect([config('app.name')]) : $restaurants->map(fn ($r) => $r->name);
@endphp
<x-layouts.site :title="__('access.invitation.title').' · '.config('app.name')" body-class="grain">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0">
        <div class="absolute -top-40 end-[-10rem] size-[38rem] rounded-full bg-teal-900/12 blur-3xl"></div>
        <div class="absolute -bottom-48 start-[-12rem] size-[34rem] rounded-full bg-brass-400/18 blur-3xl"></div>
    </div>

    <div class="fixed inset-x-0 top-0 z-20 flex justify-end p-4 sm:p-6">
        <a href="{{ route('locale.switch', $next) }}" lang="{{ $next }}" class="rounded-full bg-white/70 px-4 py-2 text-sm font-medium text-ink-700 shadow-[var(--shadow-hairline)] backdrop-blur transition hover:bg-white">
            {{ Locales::native($next) }}
        </a>
    </div>

    <main id="main" class="relative z-10 grid min-h-dvh place-items-center px-4 py-20">
        <div class="bezel reveal w-full max-w-md">
            <div class="bezel-core px-7 py-10 sm:px-10">
                <div class="flex justify-center text-ink-950"><x-brand.logo /></div>

                <p class="eyebrow mx-auto mt-8 flex w-fit">
                    <span class="size-1.5 rounded-full bg-brass-500"></span>
                    {{ __('access.invitation.eyebrow') }}
                </p>

                <h1 class="display mt-5 text-center text-3xl text-ink-950">
                    {{ __('access.invitation.heading', ['name' => \Illuminate\Support\Str::of($user->name)->explode(' ')->first()]) }}
                </h1>
                <p class="mt-3 text-center text-[0.9375rem] leading-relaxed text-ink-600">
                    {{ __('access.invitation.lead', ['place' => $places->join('، ')]) }}
                </p>

                <form method="POST" action="{{ route('invitation.accept', $token) }}" class="mt-8 space-y-5" x-data="{ reveal: false, busy: false }" x-on:submit="busy = true">
                    @csrf

                    <div class="rounded-2xl bg-ivory-100 px-4 py-3 text-sm">
                        <span class="text-ink-500">{{ __('access.invitation.email') }}</span>
                        <span class="block font-medium text-ink-900" dir="ltr">{{ $user->email }}</span>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-ink-800">{{ __('access.invitation.password') }}</label>
                        <div class="relative mt-1.5">
                            <input id="password" name="password" :type="reveal ? 'text' : 'password'" type="password" required autocomplete="new-password" autofocus
                                   aria-describedby="password-hint @error('password') password-error @enderror"
                                   class="block w-full rounded-xl border-0 bg-white px-4 py-3 pe-12 text-ink-950 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/10 transition focus:ring-2 focus:ring-teal-600 @error('password') ring-status-new @enderror">
                            <button type="button" x-on:click="reveal = ! reveal" class="absolute inset-y-0 end-0 grid w-12 place-items-center text-ink-500 hover:text-ink-900"
                                    :aria-label="reveal ? @js(__('access.invitation.hide')) : @js(__('access.invitation.show'))">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.25 12s3.75-7.5 9.75-7.5S21.75 12 21.75 12s-3.75 7.5-9.75 7.5S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <p id="password-hint" class="mt-1.5 text-xs text-ink-500">{{ __('access.invitation.password_hint') }}</p>
                        @error('password')
                            <p id="password-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-ink-800">{{ __('access.invitation.password_confirmation') }}</label>
                        <input id="password_confirmation" name="password_confirmation" :type="reveal ? 'text' : 'password'" type="password" required autocomplete="new-password"
                               class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-ink-950 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/10 transition focus:ring-2 focus:ring-teal-600">
                    </div>

                    <button type="submit" class="btn btn-primary w-full justify-between" :disabled="busy" :class="busy && 'opacity-80'">
                        <span>{{ __('access.invitation.submit') }}</span>
                        <span class="btn-icon" aria-hidden="true">
                            <svg x-show="! busy" class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </main>
</x-layouts.site>
