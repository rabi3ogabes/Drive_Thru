<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Team\Schemas;

use App\Domain\Access\Models\Position;
use App\Domain\Staff\StaffGuard;
use App\Filament\App\Workspace;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Who they are, what they do (position) and where (branches). Only positions
 * and branches the current person may hand out are offered.
 */
final class EmployeeForm
{
    /**
     * @return array<Component>
     */
    public static function schema(bool $editing = false): array
    {
        return [
            Section::make(__('staff.form.person'))
                ->compact()
                ->schema([
                    TextInput::make('name')->label(__('staff.fields.name'))->required()->maxLength(120),
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        TextInput::make('email')
                            ->label(__('staff.fields.email'))
                            ->email()
                            ->required()
                            ->maxLength(190)
                            ->disabled($editing)
                            ->dehydrated(! $editing)
                            ->helperText(fn () => $editing ? null : __('staff.fields.email_hint'))
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('phone')
                            ->label(__('staff.fields.phone'))
                            ->tel()
                            ->maxLength(30)
                            ->helperText(__('staff.fields.phone_hint'))
                            ->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                ]),
            Section::make(__('staff.form.role'))
                ->compact()
                ->schema([
                    Select::make('position_id')
                        ->label(__('staff.fields.position'))
                        ->options(fn (): array => self::positionOptions())
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText(fn (Get $get): ?string => self::positionDescription($get('position_id'))),
                    Toggle::make('all_branches')
                        ->label(__('staff.fields.all_branches'))
                        ->helperText(__('staff.fields.all_branches_hint'))
                        ->visible(fn (): bool => Workspace::branchIds() === null)
                        ->live(),
                    CheckboxList::make('branch_ids')
                        ->label(__('staff.fields.branches'))
                        ->options(fn (): array => Workspace::branchOptions())
                        ->columns(['default' => 1, 'sm' => 2])
                        ->bulkToggleable()
                        ->required(fn (Get $get): bool => ! $get('all_branches'))
                        ->visible(fn (Get $get): bool => ! $get('all_branches'))
                        ->noSearchResultsMessage(__('staff.fields.no_branches')),
                ]),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function positionOptions(): array
    {
        $guard = app(StaffGuard::class);
        $user = Workspace::user();
        $restaurant = Workspace::restaurant();

        return Position::query()
            ->orderBy('sort')
            ->get()
            ->filter(fn (Position $position): bool => $guard->canGrant($user, $restaurant, $position->permissions))
            ->mapWithKeys(fn (Position $position): array => [$position->id => $position->name])
            ->all();
    }

    private static function positionDescription(mixed $positionId): ?string
    {
        if (! is_numeric($positionId)) {
            return null;
        }

        return Position::query()->find((int) $positionId)?->description;
    }
}
