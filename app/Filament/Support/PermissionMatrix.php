<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Access\Contracts\Permission;
use BackedEnum;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Collection;

/**
 * A permission editor grouped by module: one checkbox list per group, each
 * permission with a plain-language description. Used for platform roles and
 * (Phase 2) restaurant positions.
 */
final class PermissionMatrix
{
    /**
     * @param  class-string<Permission&BackedEnum>  $enum
     * @param  ?Closure(string): bool  $isLocked  permissions the editor may not hand out are shown but disabled
     * @return array<Section>
     */
    public static function sections(string $enum, ?Closure $isLocked = null): array
    {
        return collect($enum::cases())
            ->groupBy(fn (Permission $permission): string => $permission->group())
            ->map(function ($permissions, string $group) use ($isLocked): Section {
                /** @var Collection<int, Permission&BackedEnum> $permissions */
                return Section::make($permissions->first()?->groupLabel())
                    ->compact()
                    ->schema([
                        CheckboxList::make("permission_groups.{$group}")
                            ->hiddenLabel()
                            ->options($permissions->mapWithKeys(fn (Permission&BackedEnum $p): array => [$p->value => $p->getLabel()])->all())
                            ->descriptions($permissions->mapWithKeys(fn (Permission&BackedEnum $p): array => [$p->value => $p->getDescription()])->all())
                            ->disableOptionWhen(fn (string $value): bool => $isLocked !== null && $isLocked($value))
                            ->bulkToggleable()
                            ->columns(['default' => 1, 'md' => 2]),
                    ]);
            })
            ->values()
            ->all();
    }

    /**
     * Stored list → per-group form state.
     *
     * @param  class-string<Permission&BackedEnum>  $enum
     * @param  iterable<(Permission&BackedEnum)|string>  $granted
     * @return array<string, array<int, string>>
     */
    public static function toForm(string $enum, iterable $granted): array
    {
        $values = collect($granted)->map(fn (mixed $p): string => $p instanceof BackedEnum ? (string) $p->value : (string) $p)->all();

        return collect($enum::cases())
            ->groupBy(fn (Permission $p): string => $p->group())
            ->map(fn ($permissions): array => $permissions
                ->map(fn (Permission&BackedEnum $p): string => (string) $p->value)
                ->filter(fn (string $value): bool => in_array($value, $values, true))
                ->values()
                ->all())
            ->all();
    }

    /**
     * Per-group form state → stored list (unknown values dropped).
     *
     * @param  class-string<Permission&BackedEnum>  $enum
     * @param  array<string, mixed>  $groups
     * @return list<string>
     */
    public static function fromForm(string $enum, array $groups): array
    {
        $valid = collect($enum::cases())->map(fn (BackedEnum $p): string => (string) $p->value)->all();

        return array_values(collect($groups)
            ->flatten()
            ->map(fn (mixed $value): string => (string) $value)
            ->filter(fn (string $value): bool => in_array($value, $valid, true))
            ->unique()
            ->all());
    }
}
