@php
    use App\Support\Money;
    $changed = $rows->filter(fn ($r) => $r['from'] !== $r['to'])->count();
@endphp
<div class="rounded-2xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <p class="px-1 pb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ trans_choice('menu.products.bulk_price.preview', $changed, ['count' => $changed]) }}
    </p>
    <div class="max-h-64 overflow-y-auto">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-950/5 dark:divide-white/5">
                @foreach ($rows->take(50) as $row)
                    <tr>
                        <td class="truncate py-1.5 pe-3 text-gray-700 dark:text-gray-200">{{ $row['product']->name }}</td>
                        <td class="whitespace-nowrap py-1.5 text-end tabular-nums text-gray-400 line-through" dir="ltr">{{ Money::format($row['from'], $currency) }}</td>
                        <td class="w-8 py-1.5 text-center text-gray-400">
                            <x-filament::icon icon="heroicon-m-arrow-long-right" class="flip-rtl mx-auto size-4" />
                        </td>
                        <td @class(['whitespace-nowrap py-1.5 text-end font-semibold tabular-nums', 'text-success-600 dark:text-success-400' => $row['to'] < $row['from'], 'text-gray-950 dark:text-white' => $row['to'] >= $row['from']]) dir="ltr">
                            {{ Money::format($row['to'], $currency) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
