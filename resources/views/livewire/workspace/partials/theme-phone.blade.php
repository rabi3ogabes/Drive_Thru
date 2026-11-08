{{-- A phone frame showing the real guest menu with the unsaved theme. --}}
<div class="relative mx-auto w-full max-w-[22rem] rounded-[2.75rem] bg-gray-950 p-2.5 shadow-2xl ring-1 ring-gray-950/10 dark:ring-white/15">
    <div class="relative overflow-hidden rounded-[2.25rem] bg-gray-100 dark:bg-gray-800">
        <iframe
            src="{{ $url }}"
            title="{{ __('themes.editor.preview') }}"
            loading="lazy"
            class="block h-[min(42rem,70dvh)] w-full border-0"
        ></iframe>
        <div wire:loading.delay.longer wire:target="draft,fontChoice,useTemplate,applyImport,coverUpload,logoUpload,resetToTemplate" class="absolute inset-0 grid place-items-center bg-white/60 backdrop-blur-[2px] dark:bg-gray-900/60">
            <x-filament::loading-indicator class="size-7 text-gray-500" />
        </div>
    </div>
</div>
