{{-- The "?" in every workspace page header: this page's guide article in a side panel, rendered with the page so it opens instantly. --}}
<div wire:ignore class="contents">
    <x-filament::modal
        id="guide-{{ $entry['key'] }}"
        slide-over
        width="lg"
        teleport="body"
        icon="heroicon-o-book-open"
        :heading="$entry['title']"
        :description="$entry['summary']"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                icon="heroicon-o-question-mark-circle"
                color="gray"
                size="lg"
                :label="__('guide.ui.help')"
                :tooltip="__('guide.ui.help')"
                data-guide="{{ $entry['key'] }}"
            />
        </x-slot>

        @include('filament.app.guide.article', ['entry' => $entry, 'compact' => true])

        <x-slot name="footer">
            <x-filament::link :href="$guide . '#' . $entry['key']" icon="heroicon-m-book-open">
                {{ __('guide.ui.full_guide') }}
            </x-filament::link>
        </x-slot>
    </x-filament::modal>
</div>
