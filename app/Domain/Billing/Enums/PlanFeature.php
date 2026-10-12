<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Capabilities a plan can include. A restaurant's effective features are its
 * plan's features plus or minus per-client overrides set by the platform.
 */
enum PlanFeature: string implements HasDescription, HasLabel
{
    case Loyalty = 'loyalty';
    case Promotions = 'promotions';
    case Campaigns = 'campaigns';
    case ArrivalTracking = 'arrival_tracking';
    case ScheduledOrders = 'scheduled_orders';
    case KitchenStations = 'kitchen_stations';
    case AdvancedReports = 'advanced_reports';
    case PosIntegration = 'pos_integration';
    case ApiAccess = 'api_access';
    case AiAssistant = 'ai_assistant';
    case CustomDomain = 'custom_domain';
    case PrioritySupport = 'priority_support';

    public function getLabel(): string
    {
        return __("billing.features.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("billing.features.{$this->value}.description");
    }
}
