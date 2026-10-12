<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Webhooks\Pages;

use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Filament\App\Resources\Webhooks\WebhookEndpointResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewWebhook extends ViewRecord
{
    protected static string $resource = WebhookEndpointResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var WebhookEndpoint $endpoint */
        $endpoint = $this->getRecord();

        return (string) parse_url($endpoint->url, PHP_URL_HOST);
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var WebhookEndpoint $endpoint */
        $endpoint = $this->getRecord();

        return $endpoint->description ?? __('integrations.webhooks.view_subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            WebhookEndpointResource::pingAction(),
            ActionGroup::make([
                WebhookEndpointResource::editAction(),
                WebhookEndpointResource::rotateAction(),
                WebhookEndpointResource::toggleAction(),
                WebhookEndpointResource::deleteAction(),
            ])->label(__('integrations.webhooks.actions.more'))->icon('heroicon-m-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    public function showSecretAction(): Action
    {
        return WebhookEndpointResource::secretAction();
    }
}
