<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Referrals\Pages;

use App\Domain\Loyalty\Models\Reward;
use App\Domain\Referrals\Models\Referral;
use App\Domain\Referrals\ReferralSettings;
use App\Filament\App\Resources\Referrals\ReferralResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The referrals list, its totals, and the invite settings (on or off, the
 * two rewards, the monthly cap per guest).
 */
class ListReferrals extends ListRecords
{
    protected static string $resource = ReferralResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('referrals.subheading');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.app.resources.referrals.summary')->viewData(fn (): array => [
                'live' => ReferralSettings::for(Workspace::restaurant())->isLive(),
                'counts' => Referral::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            ]),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label(__('referrals.settings.action'))
                ->icon('heroicon-m-cog-6-tooth')
                ->color(fn (): string => ReferralSettings::for(Workspace::restaurant())->isLive() ? 'gray' : 'primary')
                ->visible(fn (): bool => Workspace::user()->can('viewAny', Referral::class))
                ->modalHeading(__('referrals.settings.heading'))
                ->modalDescription(__('referrals.settings.description'))
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel(__('referrals.settings.save'))
                ->fillForm(function (): array {
                    $settings = ReferralSettings::for(Workspace::restaurant());

                    return [
                        'enabled' => $settings->enabled,
                        'referrer_reward_id' => $settings->referrerRewardId,
                        'friend_reward_id' => $settings->friendRewardId,
                        'monthly_cap' => $settings->monthlyCap,
                    ];
                })
                ->schema(fn (): array => $this->settingsSchema())
                ->action(function (array $data): void {
                    $restaurant = Workspace::restaurant();
                    ReferralSettings::save($restaurant, $data);
                    activity()->performedOn($restaurant)->causedBy(Workspace::user())->event('referrals.settings_updated')
                        ->withProperties(['restaurant_id' => $restaurant->id, 'enabled' => (bool) ($data['enabled'] ?? false)])->log('referrals.settings_updated');
                    Notification::make()->success()->title(__('referrals.settings.saved'))->send();
                }),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function settingsSchema(): array
    {
        $rewards = Reward::query()->with('product')->where('is_active', true)->whereNull('archived_at')->orderBy('id')->get()
            ->mapWithKeys(fn (Reward $reward): array => [$reward->id => $reward->name.' · '.$reward->gives(Workspace::restaurant()->currency)])
            ->all();
        $on = fn (Get $get): bool => (bool) $get('enabled');

        return [
            Text::make(__('referrals.settings.no_rewards'))->color('warning')->visible($rewards === []),
            Toggle::make('enabled')->label(__('referrals.settings.enabled'))->live()->disabled($rewards === []),
            Select::make('referrer_reward_id')->label(__('referrals.settings.referrer_reward'))
                ->options($rewards)->in(array_keys($rewards))->required($on)->native(false),
            Select::make('friend_reward_id')->label(__('referrals.settings.friend_reward'))
                ->options($rewards)->in(array_keys($rewards))->required($on)->native(false)
                ->helperText(__('referrals.settings.reward_hint')),
            TextInput::make('monthly_cap')->label(__('referrals.settings.monthly_cap'))->helperText(__('referrals.settings.monthly_cap_hint'))
                ->integer()->minValue(1)->maxValue(ReferralSettings::MAX_CAP)->required()->default(ReferralSettings::DEFAULT_CAP),
            Text::make(__('referrals.settings.checks'))->color('gray'),
        ];
    }
}
