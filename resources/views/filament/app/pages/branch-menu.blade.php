@php
    $tiles = $this->branchTiles();
@endphp
<x-filament-panels::page>
    @if ($tiles === [])
        <x-filament::section>
            <div class="py-10 text-center">
                <x-filament::icon icon="heroicon-o-building-storefront" class="mx-auto size-10 text-gray-400" />
                <p class="mt-4 font-semibold text-gray-950 dark:text-white">{{ __('menu.branch_menu.no_branches') }}</p>
            </div>
        </x-filament::section>
    @else
        <div class="flex gap-3 overflow-x-auto pb-1 [scrollbar-width:thin]" role="tablist" aria-label="{{ __('menu.branch_menu.choose') }}">
            @foreach ($tiles as $tile)
                @php($active = $tile['id'] === $this->branchId)
                <button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}"
                        wire:click="selectBranch({{ $tile['id'] }})" wire:key="tile-{{ $tile['id'] }}"
                        @class([
                            'flex min-w-44 shrink-0 items-center gap-3 rounded-2xl px-4 py-3 text-start ring-1 transition',
                            'bg-primary-600 text-white shadow-sm ring-primary-600 dark:bg-primary-500' => $active,
                            'bg-white text-gray-900 ring-gray-950/10 hover:ring-primary-600/40 dark:bg-white/5 dark:text-white dark:ring-white/10' => ! $active,
                        ])>
                    <x-filament::icon icon="heroicon-o-building-storefront" @class(['size-6 shrink-0', 'text-white/80' => $active, 'text-gray-400' => ! $active]) />
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{ $tile['name'] }}</span>
                        <span @class(['block text-xs', 'text-white/75' => $active, 'text-gray-500 dark:text-gray-400' => ! $active && $tile['off'] === 0, 'font-medium text-danger-600 dark:text-danger-400' => ! $active && $tile['off'] > 0])>
                            {{ $tile['off'] === 0 ? __('menu.branch_menu.all_on') : trans_choice('menu.branch_menu.off_count', $tile['off'], ['count' => $tile['off']]) }}
                        </span>
                    </span>
                </button>
            @endforeach
        </div>

        {{ $this->table }}
    @endif
</x-filament-panels::page>
