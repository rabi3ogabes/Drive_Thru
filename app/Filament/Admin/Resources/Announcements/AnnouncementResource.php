<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Announcements;

use App\Domain\Platform\Enums\AnnouncementAudience;
use App\Domain\Platform\Enums\AnnouncementLevel;
use App\Domain\Platform\Models\Announcement;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\Support\Bilingual;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('platform.announcements.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('platform.announcements.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Bilingual::text('title', __('platform.announcements.fields.title'), maxLength: 120),
            Bilingual::textarea('body', __('platform.announcements.fields.body'), maxLength: 500),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                ToggleButtons::make('level')
                    ->label(__('platform.announcements.fields.level'))
                    ->options(AnnouncementLevel::class)
                    ->default(AnnouncementLevel::Info)
                    ->inline()
                    ->required(),
                ToggleButtons::make('audience')
                    ->label(__('platform.announcements.fields.audience'))
                    ->options(AnnouncementAudience::class)
                    ->default(AnnouncementAudience::Everyone)
                    ->inline()
                    ->required(),
                DateTimePicker::make('starts_at')->label(__('platform.announcements.fields.starts_at'))->native(false)->seconds(false),
                DateTimePicker::make('ends_at')->label(__('platform.announcements.fields.ends_at'))->native(false)->seconds(false)->after('starts_at'),
            ]),
            Bilingual::text('action_label', __('platform.announcements.fields.action_label'), required: false, maxLength: 40),
            TextInput::make('action_url')->label(__('platform.announcements.fields.action_url'))->url()->maxLength(500),
            Toggle::make('is_active')->label(__('platform.announcements.fields.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('platform.announcements.fields.title'))
                    ->description(fn (Announcement $record): ?string => Str::limit((string) $record->body, 90) ?: null)
                    ->weight('semibold')
                    ->wrap()
                    ->extraAttributes(['class' => 'min-w-64 max-w-md'])
                    ->searchable(['title->en', 'title->ar']),
                TextColumn::make('level')->label(__('platform.announcements.fields.level'))->badge(),
                TextColumn::make('audience')->label(__('platform.announcements.fields.audience'))->badge()->color('gray'),
                TextColumn::make('starts_at')
                    ->label(__('platform.announcements.fields.window'))
                    ->state(fn (Announcement $record): string => ($record->starts_at?->format('Y-m-d H:i') ?? __('platform.announcements.now')).' → '.($record->ends_at?->format('Y-m-d H:i') ?? __('platform.announcements.until_off'))),
                TextColumn::make('dismissed_by_count')->label(__('platform.announcements.fields.dismissed'))->counts('dismissedBy')->alignCenter(),
                ToggleColumn::make('is_active')->label(__('platform.announcements.fields.is_active')),
            ])
            ->recordActions([EditAction::make()->mutateRecordDataUsing(fn (Announcement $record, array $data): array => array_merge($data, [
                'title' => $record->getTranslations('title'),
                'body' => $record->getTranslations('body'),
                'action_label' => $record->getTranslations('action_label'),
            ])), DeleteAction::make()])
            ->emptyStateHeading(__('platform.announcements.empty'))
            ->emptyStateDescription(__('platform.announcements.empty_hint'))
            ->emptyStateIcon('heroicon-o-megaphone');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAnnouncements::route('/'),
        ];
    }
}
