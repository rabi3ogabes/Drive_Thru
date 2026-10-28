<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Crm\CrmSettings;
use App\Domain\Crm\Segments;
use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
 * The thresholds behind guest segments and the guard rails for offers. Saving
 * regroups the restaurant's guests in the background.
 *
 * @property-read Schema $form
 */
class GuestSettings extends Page
{
    protected static ?string $slug = 'guest-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 9;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Workspace::can(RestaurantPermission::ManageCampaigns) || Workspace::can(RestaurantPermission::ManageSettings);
    }

    public static function getNavigationLabel(): string
    {
        return __('crm.settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('crm.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('crm.settings.subheading');
    }

    public function mount(): void
    {
        $this->form->fill(CrmSettings::for(Workspace::restaurant())->all());
    }

    public function form(Schema $schema): Schema
    {
        $number = fn (string $key, string $unit, int $min, int $max): TextInput => TextInput::make($key)
            ->label(__('crm.settings.fields.'.$key))
            ->numeric()->integer()->required()->minValue($min)->maxValue($max)
            ->suffix(__('crm.settings.units.'.$unit));

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('crm.settings.groups'))
                    ->description(__('crm.settings.groups_hint'))
                    ->icon('heroicon-o-squares-2x2')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        $number('new_days', 'days', 1, 365),
                        $number('regular_orders', 'orders', 2, 100),
                        $number('vip_top_percent', 'percent', 1, 50),
                        $number('high_spender_factor', 'times', 2, 10),
                        $number('at_risk_days', 'days', 7, 365),
                        $number('inactive_days', 'days', 14, 730)
                            ->gt('at_risk_days')
                            ->validationMessages(['gt' => __('crm.settings.inactive_after_risk')]),
                        $number('frequent_orders', 'orders', 2, 100),
                    ]),
                Section::make(__('crm.settings.messages'))
                    ->description(__('crm.settings.messages_hint'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        $number('daily_message_limit', 'messages', 10, 100_000),
                        // Wall-clock times in the restaurant's own time zone: no display-time shift.
                        TimePicker::make('quiet_start')->label(__('crm.settings.fields.quiet_start'))->seconds(false)->timezone('UTC')->required(),
                        TimePicker::make('quiet_end')->label(__('crm.settings.fields.quiet_end'))->seconds(false)->timezone('UTC')->required()->different('quiet_start'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('guest-settings')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('settings.save'))->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $restaurant = Workspace::restaurant();
        CrmSettings::save($restaurant, $this->form->getState());

        activity()->performedOn($restaurant)->causedBy(Workspace::user())->event('crm.settings_updated')
            ->withProperties(['restaurant_id' => $restaurant->id])->log('crm.settings_updated');

        $id = $restaurant->id;
        dispatch(function () use ($id): void {
            $restaurant = Restaurant::query()->find($id);

            if ($restaurant !== null) {
                app(Segments::class)->compute($restaurant);
            }
        });

        Notification::make()->success()->title(__('crm.settings.saved'))->body(__('crm.settings.saved_body'))->send();
    }
}
