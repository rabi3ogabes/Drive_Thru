<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuThemes\Tables;

use App\Domain\Themes\Models\MenuTheme;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuThemesTable
{
    public static function configure(Table $table): Table
    {
        $usage = null;
        $used = function (MenuTheme $theme) use (&$usage): int {
            $usage ??= MenuTheme::usage();

            return $usage[$theme->key] ?? 0;
        };

        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ViewColumn::make('swatch')->label('')->view('filament.admin.menu-themes.swatch')->width('6rem'),
                TextColumn::make('name')
                    ->label(__('themes.admin.fields.name'))
                    ->weight('semibold')
                    ->description(fn (MenuTheme $theme): string => $theme->getTranslation('description', app()->getLocale()))
                    ->searchable(['name->en', 'name->ar', 'key']),
                TextColumn::make('mode')
                    ->label(__('themes.admin.fields.mode'))
                    ->state(fn (MenuTheme $theme): string => ($theme->spec['mode'] ?? 'light') === 'dark' ? __('themes.editor.mode_dark') : __('themes.editor.mode_light'))
                    ->badge()->color('gray'),
                TextColumn::make('used_by')
                    ->label(__('themes.admin.fields.used_by'))
                    ->state($used)
                    ->numeric(),
                IconColumn::make('is_default')->label(__('themes.admin.fields.default'))->boolean()->trueIcon('heroicon-s-star')->falseIcon('heroicon-o-minus')->trueColor('warning'),
                IconColumn::make('is_active')->label(__('themes.admin.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label(__('themes.admin.actions.duplicate'))
                    ->icon('heroicon-m-document-duplicate')
                    ->authorize('create')
                    ->action(function (MenuTheme $record): void {
                        $copy = $record->replicate();
                        $base = Str::limit($record->key, 50, '').'-copy';
                        $key = $base;
                        for ($i = 2; MenuTheme::query()->where('key', $key)->exists(); $i++) {
                            $key = "{$base}-{$i}";
                        }
                        $copy->fill([
                            'key' => $key,
                            'name' => ['en' => $record->getTranslation('name', 'en').' (copy)', 'ar' => $record->getTranslation('name', 'ar').' (نسخة)'],
                            'is_built_in' => false,
                            'is_default' => false,
                            'sort' => (int) MenuTheme::query()->max('sort') + 1,
                        ])->save();
                        self::audit($copy, 'menu_theme.created', 'Theme template duplicated');
                        Notification::make()->success()->title(__('themes.admin.duplicated'))->send();
                    }),
                Action::make('setDefault')
                    ->label(__('themes.admin.actions.set_default'))
                    ->icon('heroicon-m-star')
                    ->color('warning')
                    ->authorize('update')
                    ->visible(fn (MenuTheme $record): bool => ! $record->is_default && $record->is_active)
                    ->requiresConfirmation()
                    ->modalDescription(__('themes.admin.set_default_body'))
                    ->action(function (MenuTheme $record): void {
                        DB::transaction(function () use ($record): void {
                            MenuTheme::query()->where('is_default', true)->update(['is_default' => false]);
                            $record->forceFill(['is_default' => true])->save();
                        });
                        self::audit($record, 'menu_theme.default_changed', 'Default theme template changed');
                        Notification::make()->success()->title(__('themes.admin.default_set'))->send();
                    }),
                Action::make('hide')
                    ->label(__('themes.admin.actions.hide'))
                    ->icon('heroicon-m-eye-slash')
                    ->color('gray')
                    ->authorize('update')
                    ->visible(fn (MenuTheme $record): bool => $record->is_active && ! $record->is_default)
                    ->requiresConfirmation()
                    ->modalDescription(__('themes.admin.hide_body'))
                    ->action(function (MenuTheme $record): void {
                        $record->forceFill(['is_active' => false])->save();
                        self::audit($record, 'menu_theme.hidden', 'Theme template hidden');
                    }),
                Action::make('show')
                    ->label(__('themes.admin.actions.show'))
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->authorize('update')
                    ->visible(fn (MenuTheme $record): bool => ! $record->is_active)
                    ->action(function (MenuTheme $record): void {
                        $record->forceFill(['is_active' => true])->save();
                        self::audit($record, 'menu_theme.updated', 'Theme template offered again');
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('themes.admin.empty'))
            ->emptyStateIcon('heroicon-o-swatch');
    }

    public static function audit(MenuTheme $theme, string $event, string $description): void
    {
        $user = auth()->user();

        activity()->performedOn($theme)->causedBy($user instanceof User ? $user : null)->event($event)
            ->withProperties(['key' => $theme->key, 'mode' => $theme->spec['mode'] ?? null])
            ->log($description);
    }
}
