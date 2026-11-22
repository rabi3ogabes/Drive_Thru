<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How the owner's live overview (/ops/{restaurant}/overview) looks, chosen
 * per restaurant by the platform team: the branch cards, or the kitchen pass.
 */
enum OpsBoardStyle: string implements HasDescription, HasLabel
{
    case Classic = 'classic';
    case Pass = 'pass';

    public function getLabel(): string
    {
        return __("ops.board_style.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("ops.board_style.{$this->value}.description");
    }
}
