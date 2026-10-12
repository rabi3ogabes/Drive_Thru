<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Api;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/** What an API key may do. Each key gets only the scopes it needs. */
enum ApiScope: string implements HasDescription, HasLabel
{
    case OrdersRead = 'orders:read';
    case OrdersWrite = 'orders:write';
    case MenuRead = 'menu:read';
    case MenuWrite = 'menu:write';

    public function getLabel(): string
    {
        return __('integrations.api.scopes.'.$this->value.'.label');
    }

    public function getDescription(): string
    {
        return __('integrations.api.scopes.'.$this->value.'.description');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $scope): string => $scope->value, self::cases());
    }
}
