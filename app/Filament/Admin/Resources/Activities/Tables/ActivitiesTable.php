<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table, bool $showSubject = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('audit.when'))
                    ->dateTime('Y-m-d H:i')
                    ->description(fn (Activity $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label(__('audit.who'))
                    ->placeholder(__('audit.system'))
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('audit.what'))
                    ->state(fn (Activity $record): string => self::describe($record))
                    ->badge()
                    ->color(fn (Activity $record): string => self::color((string) $record->event)),
                TextColumn::make('subject_type')
                    ->label(__('audit.subject'))
                    ->visible($showSubject)
                    ->state(fn (Activity $record): string => self::subjectLabel($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label(__('audit.who'))
                    ->options(fn (): array => User::query()
                        ->whereIn('id', Activity::query()->where('causer_type', (new User)->getMorphClass())->distinct()->pluck('causer_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('subject_type')
                    ->label(__('audit.subject'))
                    ->options(fn (): array => Activity::query()->distinct()->whereNotNull('subject_type')->pluck('subject_type')
                        ->mapWithKeys(fn (string $type): array => [$type => __('audit.types.'.Str::snake(class_basename($type)))])
                        ->all())
                    ->visible($showSubject),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('audit.from'))->native(false),
                        DatePicker::make('until')->label(__('audit.until'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date)))
                    ->columns(2),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (Activity $record): string => self::describe($record))
                    ->modalContent(fn (Activity $record) => view('filament.admin.activity-details', ['activity' => $record]))
                    ->modalSubmitAction(false),
            ])
            ->emptyStateHeading(__('audit.empty'))
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function describe(Activity $activity): string
    {
        $event = (string) ($activity->event ?? '');
        $key = 'audit.events.'.str_replace('.', '_', $event);

        if (__($key) !== $key) {
            return __($key);
        }

        return __('audit.generic', [
            'event' => __('audit.verbs.'.$event) !== 'audit.verbs.'.$event ? __('audit.verbs.'.$event) : $event,
            'type' => self::subjectType($activity),
        ]);
    }

    private static function subjectType(Activity $activity): string
    {
        return $activity->subject_type !== null
            ? __('audit.types.'.Str::snake(class_basename($activity->subject_type)))
            : '';
    }

    private static function subjectLabel(Activity $activity): string
    {
        $subject = $activity->subject;
        $attributes = $subject?->getAttributes() ?? [];
        $field = collect(['name', 'number', 'restaurant_name', 'email'])->first(fn (string $f): bool => array_key_exists($f, $attributes));
        $name = $field !== null ? $subject?->getAttribute($field) : null;

        return trim(self::subjectType($activity).($name ? ' · '.$name : ($activity->subject_id ? ' #'.$activity->subject_id : '')));
    }

    private static function color(string $event): string
    {
        return match (true) {
            in_array($event, ['created', 'client.onboarded', 'user.invitation_accepted', 'restaurant.reactivated'], true) => 'success',
            in_array($event, ['deleted', 'restaurant.suspended', 'sessions.revoked'], true) => 'danger',
            str_starts_with($event, 'impersonation') => 'warning',
            default => 'gray',
        };
    }
}
