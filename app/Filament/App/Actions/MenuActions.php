<?php

declare(strict_types=1);

namespace App\Filament\App\Actions;

use App\Domain\Menu\Actions\BulkPriceUpdate;
use App\Domain\Menu\Actions\DuplicateProduct;
use App\Domain\Menu\MenuSpreadsheet;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Filament\App\Resources\Menu\ProductResource;
use App\Filament\App\Workspace;
use App\Support\Lang;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menu-wide tools: duplicate, bulk price update with preview, move or
 * switch many items at once, and the Excel import/export.
 */
final class MenuActions
{
    public static function duplicate(): Action
    {
        return Action::make('duplicate')
            ->label(__('menu.products.actions.duplicate'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('menu.products.duplicate_description'))
            ->authorize(fn (Product $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Product $record, Component $livewire): void {
                $copy = app(DuplicateProduct::class)->handle($record, Workspace::user());
                Notification::make()->success()->title(__('menu.products.notifications.duplicated'))->send();
                $livewire->redirect(ProductResource::getUrl('edit', ['record' => $copy]), navigate: true);
            });
    }

    public static function bulkPrice(): BulkAction
    {
        return BulkAction::make('bulkPrice')
            ->label(__('menu.products.actions.bulk_price'))
            ->icon('heroicon-o-banknotes')
            ->modalHeading(__('menu.products.bulk_price.heading'))
            ->modalDescription(__('menu.products.bulk_price.description'))
            ->modalWidth(Width::TwoExtraLarge)
            ->schema([
                ToggleButtons::make('mode')
                    ->label(__('menu.products.bulk_price.mode'))
                    ->options([BulkPriceUpdate::PERCENT => __('menu.products.bulk_price.percent'), BulkPriceUpdate::FIXED => __('menu.products.bulk_price.fixed')])
                    ->default(BulkPriceUpdate::PERCENT)
                    ->inline()
                    ->live()
                    ->required(),
                TextInput::make('value')
                    ->label(__('menu.products.bulk_price.value'))
                    ->helperText(__('menu.products.bulk_price.value_hint'))
                    ->numeric()
                    ->required()
                    ->live(debounce: 400)
                    ->suffix(fn (Get $get): string => $get('mode') === BulkPriceUpdate::FIXED ? Workspace::restaurant()->currency : '%'),
                Select::make('round_to')
                    ->label(__('menu.products.bulk_price.round_to'))
                    ->options(fn (): array => self::roundingOptions())
                    ->default('1')
                    ->live()
                    ->native(false),
                Html::make(fn (Get $get, Component $livewire): HtmlString => self::preview($get, $livewire)),
            ])
            ->action(function (Collection $records, array $data): void {
                $count = app(BulkPriceUpdate::class)->apply(self::computePreview(self::products($records), $data), Workspace::user());
                Notification::make()->success()->title(trans_choice('menu.products.bulk_price.done', $count, ['count' => $count]))->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function moveToSection(): BulkAction
    {
        return BulkAction::make('move')
            ->label(__('menu.products.actions.move'))
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Select::make('menu_category_id')
                    ->label(__('menu.products.fields.section'))
                    ->options(fn (): array => MenuCategory::query()->orderBy('sort')->get()->mapWithKeys(fn (MenuCategory $c): array => [$c->id => $c->name])->all())
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                foreach (self::products($records) as $product) {
                    $product->update(['menu_category_id' => (int) $data['menu_category_id']]);
                }

                Notification::make()->success()->title(__('menu.products.notifications.moved'))->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function setAvailable(bool $available): BulkAction
    {
        return BulkAction::make($available ? 'switchOn' : 'switchOff')
            ->label($available ? __('menu.products.actions.switch_on') : __('menu.products.actions.switch_off'))
            ->icon($available ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
            ->color($available ? 'success' : 'gray')
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($available): void {
                foreach (self::products($records) as $product) {
                    $product->update(['is_available' => $available]);
                }

                Notification::make()->success()->title(__('menu.products.notifications.updated'))->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function export(bool $template = false): Action
    {
        return Action::make($template ? 'template' : 'export')
            ->label($template ? __('menu.products.actions.template') : __('menu.products.actions.export'))
            ->icon($template ? 'heroicon-o-document-arrow-down' : 'heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function () use ($template): BinaryFileResponse {
                $restaurant = Workspace::restaurant();
                $path = storage_path('app/private/exports/'.Str::uuid().'.xlsx');

                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0775, true);
                }

                app(MenuSpreadsheet::class)->export($restaurant, $path, $template);

                $name = $restaurant->slug.'-menu'.($template ? '-template' : '-'.now()->format('Y-m-d')).'.xlsx';

                return response()->download($path, $name)->deleteFileAfterSend();
            });
    }

    public static function import(): Action
    {
        return Action::make('import')
            ->label(__('menu.products.actions.import'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading(__('menu.products.import.heading'))
            ->modalDescription(__('menu.products.import.description'))
            ->modalSubmitActionLabel(__('menu.products.import.submit'))
            ->schema([
                FileUpload::make('file')
                    ->hiddenLabel()
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(5120)
                    ->required(),
            ])
            ->extraModalFooterActions([self::export(template: true)])
            ->action(function (array $data): void {
                $restaurant = Workspace::restaurant();
                $path = Storage::disk('local')->path((string) $data['file']);
                $sheet = app(MenuSpreadsheet::class);
                $parsed = $sheet->parse($restaurant, $path);
                @unlink($path);

                if ($parsed['errors'] !== []) {
                    Notification::make()->danger()->persistent()
                        ->title(trans_choice('menu.products.import.failed', count($parsed['errors']), ['count' => count($parsed['errors'])]))
                        ->body(fn (): HtmlString => new HtmlString(collect($parsed['errors'])->take(6)
                            ->map(fn (array $e): string => e(__('menu.products.import.row', ['row' => $e['row']]).' '.$e['message']))
                            ->join('<br>').(count($parsed['errors']) > 6 ? '<br>…' : '')))
                        ->send();

                    throw new Halt;
                }

                $result = $sheet->apply($restaurant, $parsed['rows'], Workspace::user());

                Notification::make()->success()
                    ->title(__('menu.products.import.done'))
                    ->body(__('menu.products.import.summary', $result))
                    ->send();
            });
    }

    /**
     * @param  list<Product>  $records
     * @param  array<string, mixed>  $data
     * @return \Illuminate\Support\Collection<int, array{product: Product, from: int, to: int}>
     */
    private static function computePreview(array $records, array $data): \Illuminate\Support\Collection
    {
        $currency = Workspace::restaurant()->currency;
        $mode = (string) ($data['mode'] ?? BulkPriceUpdate::PERCENT);
        $value = is_numeric($data['value'] ?? null) ? (float) $data['value'] : 0.0;

        if ($mode === BulkPriceUpdate::FIXED) {
            $value = Money::toMinor((string) $value, $currency);
        }

        return app(BulkPriceUpdate::class)->preview($records, $mode, $value, max(1, (int) ($data['round_to'] ?? 1)));
    }

    private static function preview(Get $get, Component $livewire): HtmlString
    {
        $action = method_exists($livewire, 'getMountedAction') ? $livewire->getMountedAction() : null;

        if (! $action instanceof BulkAction || ! is_numeric($get('value'))) {
            return new HtmlString('');
        }

        $rows = self::computePreview(self::products($action->getSelectedRecords()), ['mode' => $get('mode'), 'value' => $get('value'), 'round_to' => $get('round_to')]);

        return new HtmlString(view('filament.app.bulk-price-preview', [
            'rows' => $rows,
            'currency' => Workspace::restaurant()->currency,
        ])->render());
    }

    /**
     * Selected table records narrowed to products (bulk actions hand over plain models).
     *
     * @param  iterable<mixed>  $records
     * @return list<Product>
     */
    public static function products(iterable $records): array
    {
        $products = [];

        foreach ($records as $record) {
            if ($record instanceof Product) {
                $products[] = $record;
            }
        }

        return $products;
    }

    /**
     * Step in minor units => label (numeric keys become ints in PHP).
     *
     * @return array<int|string, string>
     */
    private static function roundingOptions(): array
    {
        $currency = Workspace::restaurant()->currency;
        $unit = 10 ** Money::decimals($currency);

        return [
            '1' => Lang::text('menu.products.bulk_price.no_rounding'),
            (string) intdiv($unit, 4) => Lang::text('menu.products.bulk_price.nearest', ['amount' => Money::format(intdiv($unit, 4), $currency)]),
            (string) intdiv($unit, 2) => Lang::text('menu.products.bulk_price.nearest', ['amount' => Money::format(intdiv($unit, 2), $currency)]),
            (string) $unit => Lang::text('menu.products.bulk_price.nearest', ['amount' => Money::format($unit, $currency)]),
        ];
    }
}
