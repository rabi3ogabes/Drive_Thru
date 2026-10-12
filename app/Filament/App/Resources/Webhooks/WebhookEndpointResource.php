<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Webhooks;

use App\Domain\Integrations\Actions\SaveWebhookEndpoint;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Integrations\Webhooks\DispatchWebhook;
use App\Domain\Integrations\Webhooks\SafeUrl;
use App\Domain\Integrations\Webhooks\WebhookEvent;
use App\Filament\App\Resources\Webhooks\Pages\ManageWebhooks;
use App\Filament\App\Resources\Webhooks\Pages\ViewWebhook;
use App\Filament\App\Resources\Webhooks\RelationManagers\DeliveriesRelationManager;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Support\Lang;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Component as Livewire;
use UnitEnum;

class WebhookEndpointResource extends Resource
{
    protected static ?string $model = WebhookEndpoint::class;

    protected static ?string $slug = 'webhooks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Restaurant;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('integrations.webhooks.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('integrations.webhooks.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('integrations.webhooks.navigation');
    }

    /**
     * @return array<Component>
     */
    public static function formSchema(): array
    {
        return [
            TextInput::make('url')
                ->label(__('integrations.webhooks.fields.url'))
                ->helperText(__('integrations.webhooks.fields.url_hint'))
                ->placeholder('https://pos.example.com/hooks/drive-thru')
                ->url()
                ->required()
                ->maxLength(500)
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ! app(SafeUrl::class)->allows(trim($value))) {
                        $fail(Lang::text('integrations.webhooks.errors.url'));
                    }
                })
                ->extraInputAttributes(['dir' => 'ltr']),
            TextInput::make('description')
                ->label(__('integrations.webhooks.fields.description'))
                ->placeholder(__('integrations.webhooks.fields.description_placeholder'))
                ->maxLength(120),
            CheckboxList::make('events')
                ->label(__('integrations.webhooks.fields.events'))
                ->options(collect(WebhookEvent::cases())->mapWithKeys(fn (WebhookEvent $e): array => [$e->value => $e->getLabel()])->all())
                ->descriptions(collect(WebhookEvent::cases())->mapWithKeys(fn (WebhookEvent $e): array => [$e->value => $e->getDescription()])->all())
                ->default(WebhookEvent::values())
                ->required()
                ->bulkToggleable(),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('url')->label(__('integrations.webhooks.fields.url'))->copyable()->extraAttributes(['dir' => 'ltr'])->columnSpan(['md' => 2]),
                    TextEntry::make('status')
                        ->label(__('integrations.webhooks.fields.status'))
                        ->state(fn (WebhookEndpoint $record): string => $record->is_active ? 'active' : 'off')
                        ->formatStateUsing(fn (string $state): string => __('integrations.webhooks.status.'.$state))
                        ->badge()
                        ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                    TextEntry::make('events')
                        ->label(__('integrations.webhooks.fields.events'))
                        ->formatStateUsing(fn (string $state): string => WebhookEvent::tryFrom($state)?->getLabel() ?? $state)
                        ->badge()
                        ->columnSpanFull(),
                    TextEntry::make('description')->label(__('integrations.webhooks.fields.description'))->placeholder('—'),
                    TextEntry::make('disabled_at')->label(__('integrations.webhooks.fields.disabled_at'))->dateTime()->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['deliveries as failed_count' => fn (Builder $query) => $query->where('status', WebhookDelivery::FAILED)->where('created_at', '>=', now()->subDay())])
                ->withMax(['deliveries as last_delivered_at' => fn (Builder $query) => $query->where('status', WebhookDelivery::DELIVERED)], 'delivered_at'))
            ->columns([
                TextColumn::make('url')
                    ->label(__('integrations.webhooks.fields.url'))
                    ->formatStateUsing(fn (string $state): string => (string) parse_url($state, PHP_URL_HOST))
                    ->description(fn (WebhookEndpoint $record): ?string => $record->description)
                    ->weight('semibold')
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('events')
                    ->label(__('integrations.webhooks.fields.events'))
                    ->state(fn (WebhookEndpoint $record): string => trans_choice('integrations.webhooks.events_count', count($record->events), ['count' => count($record->events)])),
                TextColumn::make('status')
                    ->label(__('integrations.webhooks.fields.status'))
                    ->state(fn (WebhookEndpoint $record): string => match (true) {
                        ! $record->is_active => 'off',
                        (int) $record->getAttribute('failed_count') > 0 => 'failing',
                        default => 'active',
                    })
                    ->formatStateUsing(fn (string $state): string => __('integrations.webhooks.status.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'failing' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_delivered_at')
                    ->label(__('integrations.webhooks.fields.last_delivered'))
                    ->since()
                    ->placeholder(__('integrations.webhooks.never')),
            ])
            ->recordUrl(fn (WebhookEndpoint $record): string => self::getUrl('view', ['record' => $record]))
            ->recordActions([
                self::pingAction(),
                ActionGroup::make([
                    self::editAction(),
                    self::rotateAction(),
                    self::toggleAction(),
                    self::deleteAction(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->emptyStateHeading(__('integrations.webhooks.empty.heading'))
            ->emptyStateDescription(__('integrations.webhooks.empty.description'));
    }

    public static function pingAction(): Action
    {
        return Action::make('ping')
            ->label(__('integrations.webhooks.actions.ping'))
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->visible(fn (WebhookEndpoint $record): bool => $record->is_active)
            ->authorize(fn (WebhookEndpoint $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (WebhookEndpoint $record): void {
                app(DispatchWebhook::class)->ping($record);
                Notification::make()->success()->title(__('integrations.webhooks.notifications.ping_sent'))->body(__('integrations.webhooks.notifications.ping_body'))->send();
            });
    }

    public static function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('integrations.webhooks.actions.edit'))
            ->icon('heroicon-o-pencil-square')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (WebhookEndpoint $record): array => ['url' => $record->url, 'description' => $record->description, 'events' => $record->events])
            ->schema(self::formSchema())
            ->authorize(fn (WebhookEndpoint $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (WebhookEndpoint $record, array $data, Action $action): void {
                try {
                    app(SaveWebhookEndpoint::class)->handle(Workspace::restaurant(), $data, Workspace::user(), $record);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('integrations.webhooks.notifications.saved'))->send();
            });
    }

    public static function rotateAction(): Action
    {
        return Action::make('rotate')
            ->label(__('integrations.webhooks.actions.rotate'))
            ->icon('heroicon-o-key')
            ->requiresConfirmation()
            ->modalHeading(__('integrations.webhooks.rotate.heading'))
            ->modalDescription(__('integrations.webhooks.rotate.description'))
            ->authorize(fn (WebhookEndpoint $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (WebhookEndpoint $record, Livewire $livewire): void {
                $secret = app(SaveWebhookEndpoint::class)->rotateSecret($record, Workspace::user());

                if (method_exists($livewire, 'replaceMountedAction')) {
                    $livewire->replaceMountedAction('showSecret', ['secret' => $secret]);
                }
            });
    }

    public static function toggleAction(): Action
    {
        return Action::make('toggle')
            ->label(fn (WebhookEndpoint $record): string => $record->is_active ? __('integrations.webhooks.actions.disable') : __('integrations.webhooks.actions.enable'))
            ->icon(fn (WebhookEndpoint $record): string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->authorize(fn (WebhookEndpoint $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (WebhookEndpoint $record): void {
                app(SaveWebhookEndpoint::class)->setActive($record, ! $record->is_active, Workspace::user());
                Notification::make()->success()->title(__('integrations.webhooks.notifications.saved'))->send();
            });
    }

    public static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('integrations.webhooks.actions.delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (WebhookEndpoint $record): string => __('integrations.webhooks.delete.heading', ['host' => (string) parse_url($record->url, PHP_URL_HOST)]))
            ->modalDescription(__('integrations.webhooks.delete.description'))
            ->authorize(fn (WebhookEndpoint $record): bool => auth()->user()?->can('delete', $record) ?? false)
            ->action(function (WebhookEndpoint $record, Livewire $livewire): void {
                activity()->performedOn($record)->causedBy(Workspace::user())->event('webhook.deleted')->withProperties(['url' => $record->url])->log('webhook.deleted');
                $record->delete();
                Notification::make()->success()->title(__('integrations.webhooks.notifications.deleted'))->send();

                if ($livewire instanceof ViewWebhook) {
                    $livewire->redirect(self::getUrl('index'));
                }
            });
    }

    /** The signing secret, shown exactly once. */
    public static function secretAction(): Action
    {
        return Action::make('showSecret')
            ->modalHeading(__('integrations.webhooks.secret.heading'))
            ->modalIcon('heroicon-o-key')
            ->modalWidth(Width::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('integrations.webhooks.secret.done'))
            ->modalContent(fn (array $arguments): Htmlable => new HtmlString(view('filament.app.secret-once', [
                'secret' => (string) ($arguments['secret'] ?? ''),
                'hint' => __('integrations.webhooks.secret.hint'),
            ])->render()));
    }

    public static function getRelations(): array
    {
        return [DeliveriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWebhooks::route('/'),
            'view' => ViewWebhook::route('/{record}'),
        ];
    }
}
