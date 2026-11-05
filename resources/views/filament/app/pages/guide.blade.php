@php
    $sections = $this->sections();
    $start = $this->startHere();
    $total = array_sum(array_map(fn (array $section): int => count($section['entries']), $sections));
    $results = [];
    foreach (range(0, $total) as $count) {
        $results[$count] = trans_choice('guide.ui.results', $count, ['count' => $count]);
    }
@endphp
<x-filament-panels::page>
    <div
        class="space-y-10"
        x-data="{
            q: '',
            open: null,
            count: null,
            results: {{ \Illuminate\Support\Js::from($results) }},
            norm(text) {
                return (text || '').toLowerCase().normalize('NFKD')
                    .replace(/[̀-ًͯ-ٰٟـ]/g, '')
                    .replace(/[أإآٱ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي')
            },
            init() {
                this.$root.querySelectorAll('[data-guide-search]').forEach((el) => el._text = this.norm(el.dataset.guideSearch))
                this.$watch('q', () => this.filter())
                this.openFromHash()
                window.addEventListener('hashchange', () => this.openFromHash())
            },
            openFromHash() {
                const key = decodeURIComponent(location.hash.slice(1))
                const article = key && document.getElementById('guide-' + key)
                if (! article) return
                this.open = key
                this.$nextTick(() => article.scrollIntoView({ block: 'start', behavior: 'smooth' }))
            },
            filter() {
                const terms = this.norm(this.q).split(/\s+/).filter(Boolean)
                let found = 0
                this.$root.querySelectorAll('[data-guide-search]').forEach((el) => {
                    const match = terms.every((term) => el._text.includes(term))
                    el.hidden = ! match
                    if (match) found++
                })
                this.$root.querySelectorAll('[data-guide-section]').forEach((section) => {
                    section.hidden = ! section.querySelector('[data-guide-search]:not([hidden])')
                })
                this.count = terms.length ? found : null
                if (terms.length && found === 1) this.open = this.$root.querySelector('[data-guide-search]:not([hidden])').dataset.key
            },
            toggle(key) {
                this.open = this.open === key ? null : key
                history.replaceState(null, '', this.open ? '#' + this.open : location.pathname + location.search)
            },
        }"
        x-on:keydown.window="if ($event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) && ! $event.target.isContentEditable) { $event.preventDefault(); $refs.search.focus() }"
    >
        {{-- Search --}}
        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-50 via-white to-white px-5 pb-4 pt-7 ring-1 ring-gray-950/5 sm:px-8 sm:pb-5 sm:pt-9 dark:from-primary-400/10 dark:via-gray-900 dark:to-gray-900 dark:ring-white/10">
            <x-filament::icon icon="heroicon-o-book-open" class="pointer-events-none absolute -end-6 -top-6 size-40 text-primary-600/[0.06] dark:text-primary-300/[0.06]" />

            <div class="relative max-w-2xl">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-xs font-medium text-primary-700 ring-1 ring-primary-600/15 dark:bg-white/5 dark:text-primary-300 dark:ring-primary-400/20">
                    <x-filament::icon icon="heroicon-m-sparkles" class="size-3.5" />
                    {{ trans_choice('guide.ui.articles', $total, ['count' => $total]) }}
                </span>

                <label for="guide-search" class="mt-4 block text-lg font-semibold text-gray-950 dark:text-white">{{ __('guide.ui.search') }}</label>

                <div class="relative mt-3">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-400" />
                    <input
                        id="guide-search"
                        type="search"
                        x-ref="search"
                        x-model.debounce.120ms="q"
                        x-on:keydown.escape="q = ''"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder="{{ __('guide.ui.search_placeholder') }}"
                        aria-describedby="guide-results"
                        class="block w-full rounded-xl border-0 bg-white py-3.5 pe-4 ps-12 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 sm:pe-28 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-500"
                    />
                    <kbd x-show="! q" class="pointer-events-none absolute end-3 top-1/2 hidden -translate-y-1/2 rounded-md bg-gray-50 px-2 py-0.5 font-sans text-xs text-gray-500 ring-1 ring-gray-950/10 sm:inline dark:bg-white/5 dark:text-gray-400 dark:ring-white/10" title="{{ __('guide.ui.search_shortcut') }}">/</kbd>
                </div>

                <p id="guide-results" class="mt-2.5 min-h-5 text-sm text-gray-500 dark:text-gray-400" aria-live="polite">
                    <span x-show="count !== null" x-text="results[count]"></span>
                    <button type="button" x-show="q" x-cloak x-on:click="q = ''; $refs.search.focus()" class="ms-2 font-medium text-primary-700 hover:underline dark:text-primary-300">{{ __('guide.ui.clear') }}</button>
                </p>
            </div>
        </section>

        {{-- Jump to a section --}}
        <nav x-show="! q" class="-mt-4 flex flex-wrap gap-2" aria-label="{{ __('guide.title') }}">
            @foreach ($sections as $key => $section)
                <a href="#section-{{ $key }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-sm text-gray-700 ring-1 ring-gray-950/10 transition hover:bg-gray-50 hover:text-gray-950 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/10 dark:hover:text-white">
                    <x-filament::icon :icon="$section['icon']" class="size-4 text-gray-400 dark:text-gray-500" />
                    {{ $section['title'] }}
                </a>
            @endforeach
        </nav>

        {{-- Start here --}}
        <section x-show="! q" aria-labelledby="guide-start">
            <h2 id="guide-start" class="text-base font-semibold text-gray-950 dark:text-white">{{ __('guide.start.title') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('guide.start.summary') }}</p>

            <ol class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($start as $index => $step)
                    <li>
                        <a
                            @if ($step['url']) href="{{ $step['url'] }}" @else href="#guide-{{ $step['key'] }}" @endif
                            class="group flex h-full items-start gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-primary-600/20 active:translate-y-0 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-400/30"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary-600 text-sm font-semibold text-white tabular-nums dark:bg-primary-500" aria-hidden="true">{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="sr-only">{{ __('guide.ui.step', ['number' => $index + 1]) }}: </span>
                                <span class="block font-medium text-gray-950 dark:text-white">{{ $step['title'] }}</span>
                                <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">{{ $step['step'] }}</span>
                            </span>
                            <x-filament::icon icon="heroicon-m-arrow-right" class="mt-1.5 size-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-primary-600 rtl:rotate-180 rtl:group-hover:-translate-x-0.5 dark:text-gray-600" />
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Sections --}}
        @foreach ($sections as $sectionKey => $section)
            <section id="section-{{ $sectionKey }}" data-guide-section class="scroll-mt-24" aria-labelledby="section-{{ $sectionKey }}-title">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-primary-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-primary-400 dark:ring-white/10">
                        <x-filament::icon :icon="$section['icon']" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 id="section-{{ $sectionKey }}-title" class="text-base font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $section['summary'] }}</p>
                    </div>
                </div>

                <div class="mt-4 divide-y divide-gray-950/5 overflow-hidden rounded-2xl bg-white ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10">
                    @foreach ($section['entries'] as $entry)
                        <article
                            id="guide-{{ $entry['key'] }}"
                            data-key="{{ $entry['key'] }}"
                            data-guide-search="{{ $entry['search'] }}"
                            class="scroll-mt-24"
                            wire:key="guide-{{ $entry['key'] }}"
                        >
                            <h3>
                                <button
                                    type="button"
                                    x-on:click="toggle(@js($entry['key']))"
                                    x-bind:aria-expanded="open === @js($entry['key'])"
                                    aria-controls="guide-{{ $entry['key'] }}-body"
                                    class="flex w-full items-center gap-4 px-4 py-4 text-start transition hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none sm:px-5 dark:hover:bg-white/[0.03] dark:focus-visible:bg-white/[0.03]"
                                >
                                    <span class="min-w-0 flex-1">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-gray-950 dark:text-white">{{ $entry['title'] }}</span>
                                            @unless ($entry['available'])
                                                <x-filament::badge color="gray" size="sm" icon="heroicon-m-lock-closed">{{ __('guide.ui.not_on_plan') }}</x-filament::badge>
                                            @endunless
                                        </span>
                                        <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400" x-show="open !== @js($entry['key'])">{{ $entry['summary'] }}</span>
                                    </span>
                                    <span class="shrink-0 text-gray-400 transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]" x-bind:class="open === @js($entry['key']) && 'rotate-180'" aria-hidden="true">
                                        <x-filament::icon icon="heroicon-m-chevron-down" class="size-5" />
                                    </span>
                                </button>
                            </h3>
                            <div id="guide-{{ $entry['key'] }}-body" x-show="open === @js($entry['key'])" x-collapse x-cloak>
                                <div class="px-4 pb-5 sm:px-5">
                                    @include('filament.app.guide.article', ['entry' => $entry, 'compact' => false])
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- No results --}}
        <div x-show="count === 0" x-cloak class="rounded-2xl bg-white px-6 py-14 text-center ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="mx-auto size-10 text-gray-300 dark:text-gray-600" />
            <p class="mt-4 font-semibold text-gray-950 dark:text-white">{{ __('guide.ui.no_results_title') }}</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('guide.ui.no_results_body') }}</p>
            <x-filament::button color="gray" size="sm" class="mt-5" x-on:click="q = ''; $refs.search.focus()">{{ __('guide.ui.clear') }}</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
