<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Webhooks\Pages;

use App\Domain\Integrations\Actions\SaveWebhookEndpoint;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Filament\App\Resources\Webhooks\WebhookEndpointResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageWebhooks extends ManageRecords
{
    protected static string $resource = WebhookEndpointResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('integrations.webhooks.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('docs')
                ->label(__('integrations.api.docs'))
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(route('api.docs'), shouldOpenInNewTab: true),
            Action::make('addWebhook')
                ->label(__('integrations.webhooks.actions.add'))
                ->icon('heroicon-m-plus')
                ->modalHeading(__('integrations.webhooks.create.heading'))
                ->modalDescription(__('integrations.webhooks.create.description'))
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel(__('integrations.webhooks.actions.create'))
                ->schema(WebhookEndpointResource::formSchema())
                ->visible(fn (): bool => Gate::allows('create', WebhookEndpoint::class))
                ->action(function (array $data, Action $action): void {
                    try {
                        [, $secret] = app(SaveWebhookEndpoint::class)->handle(Workspace::restaurant(), $data, Workspace::user());
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title((string) collect($e->errors())->flatten()->first())->send();
                        $action->halt();

                        return;
                    }

                    $this->replaceMountedAction('showSecret', ['secret' => $secret]);
                }),
        ];
    }

    public function showSecretAction(): Action
    {
        return WebhookEndpointResource::secretAction();
    }
}
