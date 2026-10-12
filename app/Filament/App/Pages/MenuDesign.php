<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Links;
use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\MenuCache;
use App\Domain\Menu\MenuLayout;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * How guests see the menu: card style, photo shape, what to show and what
 * leads. "Preview as guest" opens the real branch menu page.
 *
 * @property-read Schema $form
 */
class MenuDesign extends Page
{
    protected static ?string $slug = 'menu/design';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Menu;

    protected static ?int $navigationSort = 6;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Workspace::can(RestaurantPermission::ManageMenu);
    }

    public static function getNavigationLabel(): string
    {
        return __('menu.design.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('menu.design.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('menu.design.subheading');
    }

    public function mount(): void
    {
        $this->form->fill(MenuLayout::for(Workspace::restaurant())->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('menu.design.sections.cards'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    ToggleButtons::make('card_style')
                        ->label(__('menu.design.fields.card_style'))
                        ->options(['grid' => __('menu.design.card.grid'), 'list' => __('menu.design.card.list')])
                        ->icons(['grid' => 'heroicon-o-squares-2x2', 'list' => 'heroicon-o-bars-3'])
                        ->inline()
                        ->required(),
                    ToggleButtons::make('image_style')
                        ->label(__('menu.design.fields.image_style'))
                        ->options(['square' => __('menu.design.image.square'), 'wide' => __('menu.design.image.wide'), 'none' => __('menu.design.image.none')])
                        ->inline()
                        ->required(),
                ]),
            Section::make(__('menu.design.sections.content'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Toggle::make('show_descriptions')->label(__('menu.design.fields.show_descriptions')),
                    Toggle::make('show_calories')->label(__('menu.design.fields.show_calories')),
                    Toggle::make('show_highlights')->label(__('menu.design.fields.show_highlights'))->helperText(__('menu.design.fields.show_highlights_hint')),
                    Toggle::make('popular_first')->label(__('menu.design.fields.popular_first'))->helperText(__('menu.design.fields.popular_first_hint')),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('menu-design')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('menu.design.save'))->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $branch = Workspace::scopeToBranches(Branch::query())->orderBy('sort')->first();

        return [
            Action::make('preview')
                ->label(__('menu.design.preview'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->disabled($branch === null)
                ->url($branch !== null ? app(Links::class)->branch(Workspace::restaurant(), $branch).'#menu' : null, shouldOpenInNewTab: true),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $restaurant = Workspace::restaurant();
        $restaurant->forceFill(['menu_layout' => MenuLayout::fromArray($this->form->getState())->toArray()])->save();
        MenuCache::bump($restaurant->id);

        Notification::make()->success()->title(__('menu.design.saved'))->send();
    }
}
