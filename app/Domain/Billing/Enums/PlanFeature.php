<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use App\Domain\Services\Enums\Service;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Capabilities a restaurant can have. Features of a service come with that
 * service; the rest are add-ons of the plan tier. Per-client overrides set by
 * the platform win over both.
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

    /** The service that brings this feature; null for plan add-ons. */
    public function service(): ?Service
    {
        return match ($this) {
            self::Loyalty, self::Promotions, self::Campaigns => Service::Rewards,
            self::ArrivalTracking, self::ScheduledOrders, self::KitchenStations, self::PosIntegration => Service::DriveThru,
            default => null,
        };
    }

    /**
     * Add-ons a plan tier can include (service features come with the service).
     *
     * @return list<self>
     */
    public static function addOns(): array
    {
        return array_values(array_filter(self::cases(), fn (self $feature): bool => $feature->service() === null));
    }

    public function getLabel(): string
    {
        return __("billing.features.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("billing.features.{$this->value}.description");
    }
}
