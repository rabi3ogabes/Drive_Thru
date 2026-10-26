{{-- What the guest will see, updated as the owner types. --}}
<div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
    <p class="mb-3 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('loyalty.stamps.preview') }}</p>
    <div class="mx-auto max-w-sm">
        <x-stamp-card :name="$name" :needed="$needed" :filled="min(2, $needed - 1)" :target="$target" :reward="$reward" :accent="$restaurant->accent_color" />
    </div>
</div>
