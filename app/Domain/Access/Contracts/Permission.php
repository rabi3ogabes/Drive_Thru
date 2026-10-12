<?php

declare(strict_types=1);

namespace App\Domain\Access\Contracts;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

interface Permission extends HasDescription, HasLabel
{
    public function getLabel(): string;

    public function getDescription(): string;

    public function groupLabel(): string;

    /** Either "platform" or "restaurant". */
    public function scope(): string;

    /** Module the permission belongs to, used to group checkboxes in editors. */
    public function group(): string;

    /** Gate ability name, e.g. "restaurant.menu.manage". */
    public function ability(): string;
}
