<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ApiKeys\Pages;

use App\Domain\Integrations\Actions\IssueApiClient;
use App\Domain\Integrations\Api\ApiScope;
use App\Domain\Integrations\Models\ApiClient;
use App\Filament\App\Resources\ApiKeys\ApiClientResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ManageApiKeys extends ManageRecords
{
    protected static string $resource = ApiClientResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('integrations.api.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('docs')
                ->label(__('integrations.api.docs'))
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(route('api.docs'), shouldOpenInNewTab: true),
            Action::make('addKey')
                ->label(__('integrations.api.actions.add'))
                ->icon('heroicon-m-plus')
                ->modalHeading(__('integrations.api.create.heading'))
                ->modalDescription(__('integrations.api.create.description'))
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel(__('integrations.api.actions.create'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('integrations.api.fields.name'))
                        ->placeholder(__('integrations.api.fields.name_placeholder'))
                        ->required()
                        ->maxLength(80),
                    CheckboxList::make('scopes')
                        ->label(__('integrations.api.fields.scopes'))
                        ->options(collect(ApiScope::cases())->mapWithKeys(fn (ApiScope $s): array => [$s->value => $s->getLabel()])->all())
                        ->descriptions(collect(ApiScope::cases())->mapWithKeys(fn (ApiScope $s): array => [$s->value => $s->getDescription()])->all())
                        ->default([ApiScope::OrdersRead->value, ApiScope::MenuRead->value])
                        ->required(),
                ])
                ->visible(fn (): bool => Gate::allows('create', ApiClient::class))
                ->action(function (array $data, Action $action): void {
                    try {
                        [, $key] = app(IssueApiClient::class)->handle(Workspace::restaurant(), (string) $data['name'], array_values((array) $data['scopes']), Workspace::user());
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title((string) collect($e->errors())->flatten()->first())->send();
                        $action->halt();

                        return;
                    }

                    $this->replaceMountedAction('showKey', ['key' => $key]);
                }),
        ];
    }

    public function showKeyAction(): Action
    {
        return Action::make('showKey')
            ->modalHeading(__('integrations.api.key.heading'))
            ->modalIcon('heroicon-o-key')
            ->modalWidth(Width::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('integrations.api.key.done'))
            ->modalContent(fn (array $arguments): Htmlable => new HtmlString(view('filament.app.secret-once', [
                'secret' => (string) ($arguments['key'] ?? ''),
                'hint' => __('integrations.api.key.hint'),
            ])->render()));
    }
}
