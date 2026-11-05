<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuThemes\Schemas;

use App\Domain\Menu\MenuLayout;
use App\Domain\Themes\Contrast;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\FontCatalog;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeSpec;
use App\Filament\Support\Bilingual;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class MenuThemeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('themes.admin.sections.identity'))
                ->columnSpanFull()
                ->schema([
                    TextInput::make('key')
                        ->label(__('themes.admin.fields.key'))
                        ->helperText(__('themes.admin.fields.key_hint'))
                        ->required()
                        ->maxLength(60)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique(ignoreRecord: true)
                        // Restaurants store the key, so it never changes once the template exists.
                        ->disabled(fn (?MenuTheme $record): bool => $record !== null)
                        ->dehydrated(fn (?MenuTheme $record): bool => $record === null),
                    Bilingual::text('name', __('themes.admin.fields.name'), maxLength: 60),
                    Bilingual::textarea('description', __('themes.admin.fields.description'), maxLength: 200),
                    Toggle::make('is_active')->label(__('themes.admin.fields.active'))->helperText(__('themes.admin.fields.active_hint'))->default(true),
                ]),
            Section::make(__('themes.admin.sections.look'))
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        Select::make('spec.mode')->label(__('themes.admin.fields.mode'))
                            ->options(['light' => __('themes.editor.mode_light'), 'dark' => __('themes.editor.mode_dark')])
                            ->default('light')->required()->native(false),
                        Select::make('spec.corners')->label(__('themes.editor.corners'))
                            ->options(collect(ThemeSpec::CORNERS)->mapWithKeys(fn (string $c): array => [$c => __("themes.corners.{$c}")])->all())
                            ->default('rounded')->required()->native(false),
                    ]),
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema(array_map(
                        fn (string $key): ColorPicker => ColorPicker::make("spec.colors.{$key}")
                            ->label(__("themes.colors.{$key}"))
                            ->helperText(__("themes.colors.{$key}_hint"))
                            ->hex()->required()->regex('/^#[0-9a-fA-F]{6}$/'),
                        ThemeSpec::COLORS,
                    )),
                    Grid::make(['default' => 1, 'md' => 2, 'lg' => 4])->schema([
                        self::fontSelect('heading_latin', 'latin', 'heading', 'fraunces'),
                        self::fontSelect('heading_arabic', 'arabic', 'heading', 'alexandria'),
                        self::fontSelect('body_latin', 'latin', 'body', 'geist'),
                        self::fontSelect('body_arabic', 'arabic', 'body', 'ibm-plex-sans-arabic'),
                    ]),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        ToggleButtons::make('spec.layout.card_style')->label(__('menu.design.fields.card_style'))
                            ->options(['grid' => __('menu.design.card.grid'), 'list' => __('menu.design.card.list')])
                            ->default('grid')->inline()->required(),
                        ToggleButtons::make('spec.layout.image_style')->label(__('menu.design.fields.image_style'))
                            ->options(['square' => __('menu.design.image.square'), 'wide' => __('menu.design.image.wide'), 'none' => __('menu.design.image.none')])
                            ->default('square')->inline()->required(),
                    ]),
                ]),
        ]);
    }

    /**
     * Turns the form's fields into a checked spec, or reports the first problem on its field.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function dehydrate(array $data, ?MenuTheme $record = null): array
    {
        $spec = (array) ($data['spec'] ?? []);
        $spec['fonts'] = [
            'heading' => ($data['heading_latin'] ?? '').'+'.($data['heading_arabic'] ?? ''),
            'body' => ($data['body_latin'] ?? '').'+'.($data['body_arabic'] ?? ''),
        ];
        $spec['layout'] = array_merge($record?->spec()->layout->toArray() ?? (new MenuLayout)->toArray(), (array) ($spec['layout'] ?? []));

        try {
            $theme = ThemeSpec::fromArray($spec + ['cover' => null]);
        } catch (ThemeRejected $e) {
            throw ValidationException::withMessages([$e->reason === 'unknown_font' ? 'data.heading_latin' : 'data.spec.colors.brand' => $e->translated()]);
        }

        $check = Contrast::check($theme);
        if (! $check['ok']) {
            throw ValidationException::withMessages([
                'data.spec.colors.text' => (new ThemeRejected('unreadable', ['ratio' => $check['errors'][0]['ratio']]))->translated(),
            ]);
        }

        $clean = $theme->toArray();
        unset($clean['cover']);
        unset($data['heading_latin'], $data['heading_arabic'], $data['body_latin'], $data['body_arabic']);

        return ['spec' => $clean] + $data;
    }

    /**
     * The virtual font fields for an existing template.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrate(array $data): array
    {
        foreach (ThemeSpec::FONT_ROLES as $role) {
            $pair = FontCatalog::pair((string) ($data['spec']['fonts'][$role] ?? ''), $role);
            $data["{$role}_latin"] = $pair['latin'];
            $data["{$role}_arabic"] = $pair['arabic'];
        }

        return $data;
    }

    private static function fontSelect(string $name, string $script, string $role, string $default): Select
    {
        $options = collect(FontCatalog::all())
            ->filter(fn (array $font): bool => $font['script'] === $script && ($role === 'heading' || $font['role'] === 'both'))
            ->map(fn (array $font): string => $font['name'])
            ->all();

        return Select::make($name)
            ->label(__("themes.fonts.{$role}").' · '.__("themes.fonts.{$script}"))
            ->options($options)
            ->default($default)
            ->required()
            ->native(false);
    }
}
