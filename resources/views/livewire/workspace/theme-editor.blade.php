@php
    $restaurant = $this->restaurant;
    $colors = $draft['colors'] ?? [];
    $contrast = $this->contrast;
    $ratios = $this->ratios;
    $latinFonts = collect($fonts)->where('script', 'latin');
    $arabicFonts = collect($fonts)->where('script', 'arabic');
    $stack = function (string $role) use ($fontChoice): string {
        $choice = $fontChoice[$role];
        $brand = $choice['brand'] !== null ? "'brand-{$choice['brand']}', " : '';

        return $brand."'dt-{$choice['latin']}', 'dt-{$choice['arabic']}', ui-sans-serif, system-ui, sans-serif";
    };
    $badge = fn (float $ratio, float $needs): array => match (true) {
        $ratio >= 4.5 => ['success', __('themes.contrast.easy')],
        $ratio >= $needs => ['warning', __('themes.contrast.large_only')],
        default => ['danger', __('themes.contrast.hard')],
    };
    $storage = \Illuminate\Support\Facades\Storage::disk('public');
@endphp
<div
    class="space-y-6"
    x-data="{ dirty: $wire.entangle('dirty') }"
    x-init="window.addEventListener('beforeunload', (event) => { if (dirty) { event.preventDefault(); event.returnValue = '' } })"
>
    <style>
        {!! $faces !!}
        @foreach ($this->brandFonts as $brandFont)
            {!! \App\Domain\Themes\BrandFonts::fontFace($brandFont) !!}
        @endforeach
    </style>

    @if ($error)
        <div role="alert" class="flex items-start gap-3 rounded-xl bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-danger-600/15 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/20">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
            <p>{{ $error }}</p>
        </div>
    @endif

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="min-w-0 space-y-6">
            {{-- 1. Templates --}}
            <x-filament::section :heading="__('themes.editor.templates')" :description="__('themes.editor.templates_hint')" icon="heroicon-o-swatch">
                @if ($this->templates->isEmpty())
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('themes.editor.no_templates') }}</p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($this->templates as $item)
                            @php
                                $spec = $item->spec();
                                $heading = \App\Domain\Themes\FontCatalog::pair($spec->fonts['heading']);
                                $current = $item->key === $template;
                            @endphp
                            <button
                                type="button"
                                wire:key="template-{{ $item->key }}"
                                wire:click="useTemplate(@js($item->key))"
                                wire:loading.attr="disabled"
                                aria-pressed="{{ $current ? 'true' : 'false' }}"
                                @class([
                                    'group relative overflow-hidden rounded-2xl p-1.5 text-start ring-1 transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-0.5 active:translate-y-0',
                                    'ring-2 ring-primary-600 dark:ring-primary-400' => $current,
                                    'ring-gray-950/10 hover:ring-gray-950/20 dark:ring-white/10 dark:hover:ring-white/20' => ! $current,
                                ])
                            >
                                <span class="block overflow-hidden rounded-[0.9rem] p-4" style="background: {{ $spec->colors['background'] }}; color: {{ $spec->colors['text'] }}">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="h-2 w-10 rounded-full" style="background: {{ $spec->colors['brand'] }}"></span>
                                        <span class="h-2 w-6 rounded-full opacity-40" style="background: {{ $spec->colors['accent'] }}"></span>
                                    </span>
                                    <span class="mt-4 block text-lg leading-tight" style="font-family: 'dt-{{ $heading['latin'] }}', 'dt-{{ $heading['arabic'] }}', serif">{{ __('themes.editor.sample') }}</span>
                                    <span class="mt-3 flex gap-1.5">
                                        @foreach (['brand', 'accent', 'background', 'text'] as $swatch)
                                            <span class="size-4 rounded-full ring-1 ring-black/10" style="background: {{ $spec->colors[$swatch] }}"></span>
                                        @endforeach
                                    </span>
                                </span>
                                <span class="flex items-center justify-between gap-2 px-2 pb-1 pt-2.5">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $item->name }}</span>
                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $spec->mode === 'dark' ? __('themes.editor.mode_dark') : __('themes.editor.mode_light') }}</span>
                                    </span>
                                    @if ($current)
                                        <x-filament::badge color="primary" size="sm" icon="heroicon-m-check">{{ __('themes.editor.current') }}</x-filament::badge>
                                    @elseif (! $item->is_active)
                                        <x-filament::badge color="gray" size="sm">{{ __('themes.editor.hidden') }}</x-filament::badge>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            {{-- 2. Colours --}}
            <x-filament::section :heading="__('themes.editor.colors')" :description="__('themes.editor.colors_hint')" icon="heroicon-o-paint-brush">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    @foreach (['brand', 'accent', 'background', 'text'] as $key)
                        <div wire:key="color-{{ $key }}" class="flex items-center gap-3 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <label class="relative size-11 shrink-0 cursor-pointer overflow-hidden rounded-lg ring-1 ring-gray-950/10 dark:ring-white/20" style="background: {{ $colors[$key] ?? '#ffffff' }}">
                                <span class="sr-only">{{ __("themes.colors.{$key}") }}</span>
                                <input type="color" wire:model.live.debounce.400ms="draft.colors.{{ $key }}" class="absolute inset-0 size-full cursor-pointer opacity-0">
                            </label>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __("themes.colors.{$key}") }}</p>
                                <p class="text-xs leading-snug text-gray-500 dark:text-gray-400">{{ __("themes.colors.{$key}_hint") }}</p>
                            </div>
                            <input
                                type="text"
                                wire:model.blur="draft.colors.{{ $key }}"
                                maxlength="7"
                                dir="ltr"
                                spellcheck="false"
                                aria-label="{{ __("themes.colors.{$key}") }} (hex)"
                                class="w-24 rounded-lg border-0 bg-white px-2.5 py-1.5 font-mono text-sm uppercase text-gray-950 ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10"
                            >
                        </div>
                    @endforeach
                </div>

                @if ($ratios !== [])
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        @foreach ($ratios as $key => $check)
                            @php
                                [$color, $label] = $badge($check['ratio'], $check['needs']);
                            @endphp
                            <div class="rounded-xl px-3 py-2.5 ring-1 ring-gray-950/5 dark:ring-white/10">
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __("themes.contrast.{$key}") }}</p>
                                <p class="mt-1 font-mono text-base font-semibold tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ number_format($check['ratio'], 1) }}:1</p>
                                <x-filament::badge :color="$color" size="sm" class="mt-1.5 w-max">{{ $label }}</x-filament::badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            {{-- 3. Fonts --}}
            <x-filament::section :heading="__('themes.editor.fonts')" :description="__('themes.editor.fonts_hint')" icon="heroicon-o-language">
                <x-slot name="afterHeader">
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-up-tray" x-on:click="$dispatch('open-modal', { id: 'theme-font-upload' })">
                        {{ __('themes.fonts.upload') }}
                    </x-filament::button>
                </x-slot>

                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    @foreach (['heading', 'body'] as $role)
                        @php
                            $usingBrand = $fontChoice[$role]['brand'] !== null;
                        @endphp
                        <div wire:key="font-{{ $role }}" class="space-y-3 rounded-xl p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __("themes.fonts.{$role}") }}</p>
                            <p class="truncate rounded-lg bg-gray-50 px-3 py-3 text-gray-950 dark:bg-white/5 dark:text-white {{ $role === 'heading' ? 'text-2xl' : 'text-base' }}" style="font-family: {{ $stack($role) }}">
                                {{ __('themes.editor.specimen') }}
                            </p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('themes.fonts.latin') }}</span>
                                    <x-filament::input.wrapper :disabled="$usingBrand">
                                        <x-filament::input.select wire:model.live="fontChoice.{{ $role }}.latin" :disabled="$usingBrand">
                                            @foreach ($latinFonts as $key => $font)
                                                @if ($role === 'heading' || $font['role'] === 'both')
                                                    <option value="{{ $key }}">{{ $font['name'] }}</option>
                                                @endif
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('themes.fonts.arabic') }}</span>
                                    <x-filament::input.wrapper :disabled="$usingBrand">
                                        <x-filament::input.select wire:model.live="fontChoice.{{ $role }}.arabic" :disabled="$usingBrand">
                                            @foreach ($arabicFonts as $key => $font)
                                                @if ($role === 'heading' || $font['role'] === 'both')
                                                    <option value="{{ $key }}">{{ $font['name'] }}</option>
                                                @endif
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </label>
                            </div>
                            @if ($this->brandFonts->isNotEmpty())
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('themes.fonts.own') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select wire:model.live="fontChoice.{{ $role }}.brand">
                                            <option value="">{{ __('themes.fonts.none') }}</option>
                                            @foreach ($this->brandFonts as $brandFont)
                                                <option value="{{ $brandFont->id }}">{{ $brandFont->name }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                    @if ($usingBrand)
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ __('themes.fonts.own_hint') }}</span>
                                    @endif
                                </label>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($this->brandFonts->isNotEmpty())
                    <ul class="mt-4 divide-y divide-gray-950/5 rounded-xl ring-1 ring-gray-950/5 dark:divide-white/5 dark:ring-white/10">
                        @foreach ($this->brandFonts as $brandFont)
                            <li wire:key="brand-font-{{ $brandFont->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-950 dark:text-white" style="font-family: '{{ $brandFont->family() }}', sans-serif">{{ $brandFont->name }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __("themes.fonts.scripts_{$brandFont->scripts}") }} · <span dir="ltr">{{ number_format($brandFont->size_bytes / 1024) }} KB</span></span>
                                </span>
                                <x-filament::icon-button icon="heroicon-m-trash" color="danger" :label="__('themes.fonts.delete')" :tooltip="__('themes.fonts.delete')"
                                    wire:click="deleteFont({{ $brandFont->id }})" wire:confirm="{{ __('themes.fonts.delete_confirm', ['name' => $brandFont->name]) }}" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>

            {{-- 4. Logo, cover and corners --}}
            <x-filament::section :heading="__('themes.editor.brand')" :description="__('themes.editor.brand_hint')" icon="heroicon-o-photo">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    <div class="flex items-center gap-4 rounded-xl p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                        <span class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            @if ($restaurant->logo_path)
                                <img src="{{ $storage->url($restaurant->logo_path) }}" alt="" class="size-full object-contain">
                            @else
                                <x-filament::icon icon="heroicon-o-building-storefront" class="size-7 text-gray-400" />
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('themes.editor.logo') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('themes.editor.logo_hint') }}</p>
                            <label class="mt-2 inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-primary-700 hover:underline dark:text-primary-400">
                                <x-filament::icon icon="heroicon-m-arrow-up-tray" class="size-4" />
                                <span wire:loading.remove wire:target="logoUpload">{{ $restaurant->logo_path ? __('themes.editor.replace') : __('themes.editor.upload') }}</span>
                                <span wire:loading wire:target="logoUpload">{{ __('themes.editor.uploading') }}</span>
                                <input type="file" wire:model="logoUpload" accept="image/png,image/jpeg,image/webp" class="sr-only">
                            </label>
                            @error('logoUpload') <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-4 rounded-xl p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                        <span class="grid h-16 w-24 shrink-0 place-items-center overflow-hidden rounded-xl bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            @if ($restaurant->cover_path)
                                <img src="{{ $storage->url($restaurant->cover_path) }}" alt="" class="size-full object-cover">
                            @else
                                <x-filament::icon icon="heroicon-o-photo" class="size-7 text-gray-400" />
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('themes.editor.cover') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('themes.editor.cover_hint') }}</p>
                            <span class="mt-2 flex flex-wrap items-center gap-3">
                                <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-primary-700 hover:underline dark:text-primary-400">
                                    <x-filament::icon icon="heroicon-m-arrow-up-tray" class="size-4" />
                                    <span wire:loading.remove wire:target="coverUpload">{{ $restaurant->cover_path ? __('themes.editor.replace') : __('themes.editor.upload') }}</span>
                                    <span wire:loading wire:target="coverUpload">{{ __('themes.editor.uploading') }}</span>
                                    <input type="file" wire:model="coverUpload" accept="image/png,image/jpeg,image/webp" class="sr-only">
                                </label>
                                @if ($restaurant->cover_path)
                                    <button type="button" wire:click="removeCover" class="text-sm text-gray-500 hover:text-danger-600 dark:text-gray-400">{{ __('themes.editor.remove') }}</button>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <fieldset class="mt-4">
                    <legend class="text-sm font-medium text-gray-950 dark:text-white">{{ __('themes.editor.corners') }}</legend>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        @foreach (['sharp' => '0.25rem', 'soft' => '0.75rem', 'rounded' => '1.5rem'] as $corner => $radius)
                            <label wire:key="corner-{{ $corner }}" @class([
                                'flex cursor-pointer flex-col items-center gap-2 rounded-xl px-3 py-3 text-sm ring-1 transition',
                                'bg-primary-50 text-primary-700 ring-2 ring-primary-600 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400' => ($draft['corners'] ?? '') === $corner,
                                'text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5' => ($draft['corners'] ?? '') !== $corner,
                            ])>
                                <input type="radio" wire:model.live="draft.corners" value="{{ $corner }}" class="sr-only">
                                <span class="h-7 w-12 border-2 border-current" style="border-radius: {{ $radius }}" aria-hidden="true"></span>
                                {{ __("themes.corners.{$corner}") }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </x-filament::section>

            {{-- 5. Theme file --}}
            <x-filament::section :heading="__('themes.editor.file')" :description="__('themes.editor.file_hint')" icon="heroicon-o-document-arrow-down" collapsible :collapsed="$pendingImport === null">
                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button color="gray" icon="heroicon-m-arrow-down-tray" wire:click="export">{{ __('themes.editor.export') }}</x-filament::button>
                    <label class="inline-flex cursor-pointer">
                        <span class="fi-btn inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:hover:bg-white/10">
                            <x-filament::icon icon="heroicon-m-arrow-up-tray" class="size-5 text-gray-400" />
                            <span wire:loading.remove wire:target="importUpload">{{ __('themes.editor.import') }}</span>
                            <span wire:loading wire:target="importUpload">{{ __('themes.editor.reading') }}</span>
                        </span>
                        <input type="file" wire:model="importUpload" accept=".json,application/json" class="sr-only">
                    </label>
                </div>

                @if ($pendingImport)
                    <div class="mt-4 rounded-xl bg-primary-50/70 p-4 ring-1 ring-primary-600/10 dark:bg-primary-400/5 dark:ring-primary-400/15">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('themes.editor.import_ready', ['name' => $pendingImport['name'][app()->getLocale() === 'ar' ? 'ar' : 'en']]) }}</p>
                        <p class="mt-1 flex gap-1.5">
                            @foreach (['brand', 'accent', 'background', 'text'] as $swatch)
                                <span class="size-5 rounded-full ring-1 ring-black/10" style="background: {{ $pendingImport['spec']['colors'][$swatch] }}"></span>
                            @endforeach
                        </p>
                        @foreach ($pendingImport['notices'] as $notice)
                            <p class="mt-2 text-xs text-warning-700 dark:text-warning-400">{{ $notice }}</p>
                        @endforeach
                        <div class="mt-3 flex gap-2">
                            <x-filament::button size="sm" wire:click="applyImport">{{ __('themes.editor.apply') }}</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="cancelImport">{{ __('themes.editor.cancel') }}</x-filament::button>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Live preview (wide screens) --}}
        <aside class="sticky top-20 hidden xl:block" aria-label="{{ __('themes.editor.preview') }}">
            @include('livewire.workspace.partials.theme-phone', ['url' => $previewUrl])
            <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">{{ __('themes.editor.preview_hint') }}</p>
        </aside>
    </div>

    {{-- Save bar --}}
    <div class="sticky bottom-3 z-10 flex items-center justify-between gap-2 rounded-2xl bg-white/90 px-3 py-2.5 shadow-lg sm:px-4 sm:py-3 ring-1 ring-gray-950/5 backdrop-blur dark:bg-gray-900/90 dark:ring-white/10">
        <p class="flex items-center gap-2 text-sm">
            <span x-show="dirty" class="flex items-center gap-2 text-warning-700 dark:text-warning-400">
                <span class="size-2 shrink-0 rounded-full bg-warning-500" aria-hidden="true"></span><span class="hidden sm:inline">{{ __('themes.editor.unsaved') }}</span>
            </span>
            <span x-show="! dirty" class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-check-circle" class="size-4 shrink-0 text-success-600" /><span class="hidden sm:inline">{{ __('themes.editor.live') }}</span>
            </span>
        </p>
        <div class="flex items-center gap-2">
            <x-filament::button color="gray" icon="heroicon-m-device-phone-mobile" class="xl:hidden" x-on:click="$dispatch('open-modal', { id: 'theme-preview' })">{{ __('themes.editor.preview') }}</x-filament::button>
            <x-filament::icon-button color="gray" icon="heroicon-m-arrow-uturn-left" size="lg" class="sm:hidden" :label="__('themes.editor.reset')" x-on:click="$dispatch('open-modal', { id: 'theme-reset' })" />
            <x-filament::button color="gray" icon="heroicon-m-arrow-uturn-left" class="hidden sm:inline-flex" x-on:click="$dispatch('open-modal', { id: 'theme-reset' })">{{ __('themes.editor.reset') }}</x-filament::button>
            <x-filament::button icon="heroicon-m-check" wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ __('themes.editor.save') }}</x-filament::button>
        </div>
    </div>

    <x-filament::modal id="theme-replace" icon="heroicon-o-swatch" :heading="__('themes.editor.replace_title')" :description="__('themes.editor.replace_body')" width="md">
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'theme-replace' })">{{ __('themes.editor.cancel') }}</x-filament::button>
                <x-filament::button wire:click="useTemplate({{ \Illuminate\Support\Js::from($pendingTemplate ?? '') }}, true)">{{ __('themes.editor.use') }}</x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="theme-reset" icon="heroicon-o-arrow-uturn-left" icon-color="warning" :heading="__('themes.editor.reset_title')" :description="__('themes.editor.reset_body')" width="md">
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'theme-reset' })">{{ __('themes.editor.cancel') }}</x-filament::button>
                <x-filament::button color="warning" wire:click="resetToTemplate">{{ __('themes.editor.reset') }}</x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="theme-font-upload" icon="heroicon-o-language" :heading="__('themes.fonts.upload')" :description="__('themes.fonts.upload_hint')" width="md">
        <div class="space-y-4">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">{{ __('themes.fonts.file') }}</span>
                <input type="file" wire:model="fontUpload" accept=".woff2,font/woff2" class="block w-full text-sm text-gray-600 file:me-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-950 dark:text-gray-300 dark:file:bg-white/10 dark:file:text-white">
                @error('fontUpload') <span class="mt-1 block text-xs text-danger-600 dark:text-danger-400">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">{{ __('themes.fonts.name') }}</span>
                <x-filament::input.wrapper :valid="! $errors->has('fontName')">
                    <x-filament::input type="text" wire:model="fontName" maxlength="60" />
                </x-filament::input.wrapper>
                @error('fontName') <span class="mt-1 block text-xs text-danger-600 dark:text-danger-400">{{ $message }}</span> @enderror
            </label>
            <fieldset>
                <legend class="mb-1 text-sm font-medium text-gray-950 dark:text-white">{{ __('themes.fonts.scripts') }}</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['latin', 'arabic', 'both'] as $script)
                        <label @class([
                            'cursor-pointer rounded-lg px-3 py-2 text-center text-sm ring-1 transition',
                            'bg-primary-50 text-primary-700 ring-2 ring-primary-600 dark:bg-primary-400/10 dark:text-primary-300' => $fontScripts === $script,
                            'text-gray-700 ring-gray-950/10 dark:text-gray-300 dark:ring-white/10' => $fontScripts !== $script,
                        ])>
                            <input type="radio" wire:model.live="fontScripts" value="{{ $script }}" class="sr-only">
                            {{ __("themes.fonts.scripts_{$script}") }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'theme-font-upload' })">{{ __('themes.editor.cancel') }}</x-filament::button>
                <x-filament::button wire:click="uploadFont" wire:loading.attr="disabled" wire:target="uploadFont,fontUpload">{{ __('themes.fonts.add') }}</x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="theme-preview" :heading="__('themes.editor.preview')" width="md">
        @include('livewire.workspace.partials.theme-phone', ['url' => $previewUrl])
    </x-filament::modal>
</div>
