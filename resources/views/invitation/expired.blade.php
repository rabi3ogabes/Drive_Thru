<x-layouts.site :title="__('access.invitation.expired.title').' · '.config('app.name')" body-class="grain">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0">
        <div class="absolute -top-40 end-[-10rem] size-[38rem] rounded-full bg-teal-900/12 blur-3xl"></div>
        <div class="absolute -bottom-48 start-[-12rem] size-[34rem] rounded-full bg-brass-400/18 blur-3xl"></div>
    </div>

    <main id="main" class="relative z-10 grid min-h-dvh place-items-center px-4 py-16">
        <div class="bezel reveal w-full max-w-md">
            <div class="bezel-core px-8 py-12 text-center sm:px-10">
                <div class="flex justify-center text-ink-950"><x-brand.logo /></div>
                <span class="mx-auto mt-10 grid size-14 place-items-center rounded-2xl bg-brass-50 text-brass-700 ring-1 ring-brass-500/20">
                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 6v6l3.5 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <h1 class="display mt-6 text-3xl text-ink-950">{{ __('access.invitation.expired.title') }}</h1>
                <p class="mx-auto mt-4 max-w-sm text-base leading-relaxed text-ink-600">{{ __('access.invitation.expired.body') }}</p>
                <div class="mt-10 flex flex-wrap justify-center gap-3">
                    <a href="{{ url('/app/login') }}" class="btn btn-primary">
                        <span>{{ __('access.invitation.expired.sign_in') }}</span>
                        <span class="btn-icon" aria-hidden="true"><svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </a>
                </div>
            </div>
        </div>
    </main>
</x-layouts.site>
