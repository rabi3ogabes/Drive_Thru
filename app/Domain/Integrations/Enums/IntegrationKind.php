<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Enums;

use Filament\Support\Contracts\HasLabel;

/** What an integration does for a restaurant (one provider per kind). */
enum IntegrationKind: string implements HasLabel
{
    case Payments = 'payments';
    case Printing = 'printing';
    case Pos = 'pos';

    public function getLabel(): string
    {
        return __("integrations.kind.{$this->value}");
    }
}
