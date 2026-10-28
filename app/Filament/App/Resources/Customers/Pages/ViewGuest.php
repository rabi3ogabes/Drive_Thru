<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Customers\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Crm\Consent;
use App\Domain\Crm\CrmSettings;
use App\Domain\Crm\GuestProfile;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\AdjustPoints;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Exceptions\NotEnoughPoints;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\Reward;
use App\Filament\App\Resources\Customers\CustomerResource;
use App\Filament\App\Workspace;
use App\Support\Digits;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One guest: visits and spend, favourites, recent orders, segments,
 * rewards and consent, with the few things staff may do for them. Only the
 * record key goes to the browser; everything shown is rendered on the server.
 */
class ViewGuest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CustomerResource::class;

    protected string $view = 'filament.app.resources.customers.view-guest';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(CustomerResource::canView($this->record), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return CustomerResource::displayName($this->guest());
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            CustomerResource::getUrl() => CustomerResource::getPluralModelLabel(),
            '#' => CustomerResource::displayName($this->guest()),
        ];
    }

    public function guest(): Customer
    {
        $record = $this->getRecord();
        assert($record instanceof Customer);

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $restaurant = Workspace::restaurant();

        return [
            'guest' => $this->guest(),
            'profile' => app(GuestProfile::class)->for($this->guest()),
            'restaurant' => $restaurant,
            'crm' => CrmSettings::for($restaurant),
            'phone' => CustomerResource::phone($this->guest()),
            'loyalty' => CustomerResource::loyalty(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $loyalty = fn (): bool => CustomerResource::loyalty() && Workspace::can(RestaurantPermission::ManageLoyalty);

        return [
            Action::make('adjustPoints')
                ->label(__('crm.guests.actions.adjust'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible($loyalty)
                ->modalHeading(__('loyalty.admin.adjust.heading'))
                ->modalDescription(__('loyalty.admin.adjust.description'))
                ->modalWidth('md')
                ->schema([
                    TextInput::make('points')->label(__('loyalty.admin.adjust.points'))->helperText(__('loyalty.admin.adjust.points_hint'))
                        ->numeric()->integer()->required()->minValue(-1_000_000)->maxValue(1_000_000)->notIn([0]),
                    TextInput::make('reason')->label(__('loyalty.admin.adjust.reason'))->required()->minLength(3)->maxLength(150),
                ])
                ->action(function (array $data, Action $action) use ($loyalty): void {
                    abort_unless($loyalty(), 403);
                    $guest = $this->guest();

                    try {
                        app(AdjustPoints::class)->handle($guest, (int) $data['points'], trim((string) $data['reason']), Workspace::user());
                    } catch (NotEnoughPoints) {
                        Notification::make()->danger()->title(__('loyalty.admin.adjust.not_enough', ['balance' => Digits::number($guest->points_balance)]))->send();
                        $action->halt();

                        return;
                    }

                    $guest->refresh();
                    Notification::make()->success()->title(__('loyalty.admin.adjust.done', ['balance' => Digits::number($guest->points_balance)]))->send();
                }),
            ActionGroup::make([
                Action::make('grantReward')
                    ->label(__('crm.guests.actions.grant'))
                    ->icon('heroicon-o-gift')
                    ->visible($loyalty)
                    ->modalHeading(__('crm.guests.actions.grant'))
                    ->modalDescription(__('crm.guests.actions.grant_hint'))
                    ->modalWidth('md')
                    ->schema([
                        Select::make('reward_id')
                            ->label(__('crm.guests.actions.grant_reward'))
                            ->options(fn (): array => Reward::query()->where('is_active', true)->whereNull('archived_at')->orderBy('points_cost')->get()
                                ->mapWithKeys(fn (Reward $reward): array => [$reward->id => $reward->name])->all())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data, Action $action) use ($loyalty): void {
                        abort_unless($loyalty(), 403);
                        $reward = Reward::query()->find((int) $data['reward_id']);

                        try {
                            abort_if($reward === null, 404);
                            app(GrantReward::class)->handle($this->guest(), $reward, 'manual', Workspace::user());
                        } catch (RewardUnavailable $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()->success()->title(__('crm.guests.actions.granted', [
                            'reward' => $reward->name,
                            'name' => CustomerResource::displayName($this->guest()),
                        ]))->send();
                    }),
                Action::make('withdrawConsent')
                    ->label(__('crm.guests.actions.withdraw'))
                    ->icon('heroicon-o-bell-slash')
                    ->color('danger')
                    ->visible(fn (): bool => $this->guest()->acceptsOffers())
                    ->requiresConfirmation()
                    ->modalHeading(__('crm.guests.actions.withdraw_heading'))
                    ->modalDescription(__('crm.guests.actions.withdraw_body'))
                    ->action(function (): void {
                        abort_unless(CustomerResource::canView($this->guest()), 403);
                        app(Consent::class)->withdraw($this->guest(), 'staff', Workspace::user());
                        Notification::make()->success()->title(__('crm.guests.actions.withdrawn'))->send();
                    }),
            ])->button()->color('gray')->label(__('crm.guests.actions.more'))->icon('heroicon-m-ellipsis-horizontal'),
        ];
    }
}
