{{-- A secret shown exactly once (webhook signing secret, API key), with copy. --}}
<div class="space-y-4" x-data="{ copied: false }">
    <div class="rounded-3xl bg-gray-950 px-5 py-6 shadow-inner dark:bg-white/5">
        <p class="break-all text-center font-mono text-base leading-relaxed text-white select-all" dir="ltr">{{ $secret }}</p>
        <div class="mt-4 flex justify-center">
            <button type="button"
                    x-on:click="navigator.clipboard?.writeText({{ \Illuminate\Support\Js::from($secret) }}); copied = true; setTimeout(() => copied = false, 1800)"
                    class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/15 transition hover:bg-white/15">
                <x-filament::icon icon="heroicon-m-clipboard-document" class="size-4" />
                <span x-show="! copied">{{ __('integrations.secret_once.copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('integrations.secret_once.copied') }}</span>
            </button>
        </div>
    </div>
    <p class="flex gap-2 rounded-2xl bg-warning-50 px-4 py-3 text-sm text-warning-800 ring-1 ring-warning-600/15 dark:bg-warning-400/10 dark:text-warning-300">
        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
        <span>{{ $hint }}</span>
    </p>
</div>
