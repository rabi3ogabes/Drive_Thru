<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Domain\Access\Actions\InviteUser;
use App\Domain\Access\Impersonation;
use App\Domain\Billing\Actions\ChangePlan;
use App\Domain\Billing\Actions\IssueInvoice;
use App\Domain\Billing\Actions\ReactivateRestaurant;
use App\Domain\Billing\Actions\SuspendRestaurant;
use App\Domain\Billing\Models\Plan;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use App\Support\WhatsApp;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Header actions of the client page.
 */
final class ClientActions
{
    public static function viewAsOwner(): Action
    {
        return Action::make('viewAsOwner')
            ->label(__('clients.impersonation.action'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (Restaurant $record): string => __('clients.impersonation.heading', ['restaurant' => $record->name]))
            ->modalDescription(__('clients.impersonation.description'))
            ->modalIcon(Heroicon::OutlinedEye)
            ->modalSubmitActionLabel(__('clients.impersonation.submit'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('clients.impersonation.reason'))
                    ->placeholder(__('clients.impersonation.reason_placeholder'))
                    ->required()
                    ->minLength(5)
                    ->maxLength(300),
            ])
            ->visible(fn (Restaurant $record): bool => Gate::allows('impersonate', $record) && $record->owner()?->is_active === true)
            ->action(function (Restaurant $record, array $data, Action $action): void {
                /** @var User $admin */
                $admin = auth()->user();

                try {
                    app(Impersonation::class)->start($admin, $record, $data['reason']);
                } catch (DomainException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();
                }

                $action->redirect('/app/'.$record->slug);
            });
    }

    public static function changePlan(): Action
    {
        return Action::make('changePlan')
            ->label(__('clients.actions.change_plan'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->modalHeading(__('clients.actions.change_plan'))
            ->modalDescription(__('clients.actions.change_plan_description'))
            ->fillForm(fn (Restaurant $record): array => [
                'plan_id' => $record->subscription?->plan_id,
                'branches' => $record->subscription->quantity ?? 1,
                'negotiated_price' => $record->subscription !== null && $record->subscription->unit_price_minor !== $record->subscription->plan->price_minor,
                'unit_price' => $record->subscription !== null ? Money::toMajor($record->subscription->unit_price_minor, $record->subscription->currency) : null,
            ])
            ->schema([
                Select::make('plan_id')
                    ->label(__('clients.fields.plan'))
                    ->options(fn (): array => Plan::query()->available()->get()->mapWithKeys(fn (Plan $p): array => [$p->id => $p->name.' · '.$p->formattedPrice()])->all())
                    ->required()
                    ->native(false),
                TextInput::make('branches')
                    ->label(__('clients.fields.branches'))
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Toggle::make('negotiated_price')->label(__('clients.fields.negotiated_price'))->live(),
                TextInput::make('unit_price')
                    ->label(__('clients.fields.unit_price'))
                    ->visible(fn (Get $get): bool => (bool) $get('negotiated_price'))
                    ->required(fn (Get $get): bool => (bool) $get('negotiated_price'))
                    ->inputMode('decimal')
                    ->rule('regex:/^[0-9٠-٩.,٫ ]+$/u'),
            ])
            ->visible(fn (Restaurant $record): bool => $record->subscription !== null && Gate::allows('manageBilling', $record))
            ->action(function (Restaurant $record, array $data): void {
                $plan = Plan::query()->findOrFail((int) $data['plan_id']);
                $subscription = $record->subscription;

                if ($subscription === null) {
                    return;
                }

                app(ChangePlan::class)->handle(
                    $subscription,
                    $plan,
                    (int) $data['branches'],
                    ! empty($data['negotiated_price']) ? Money::toMinor((string) $data['unit_price'], $plan->currency) : null,
                );

                Notification::make()->success()->title(__('clients.notifications.plan_changed', ['plan' => $plan->name]))->send();
            });
    }

    public static function issueInvoice(): Action
    {
        return Action::make('issueInvoice')
            ->label(__('clients.actions.issue_invoice'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('clients.actions.issue_invoice'))
            ->modalDescription(fn (Restaurant $record): string => __('clients.actions.issue_invoice_description', [
                'amount' => Money::format($record->subscription?->amountPerPeriodMinor() ?? 0, $record->subscription->currency ?? 'QAR'),
            ]))
            ->visible(fn (Restaurant $record): bool => Gate::allows('manageBilling', $record)
                && $record->subscription !== null
                && $record->subscription->amountPerPeriodMinor() > 0
                && $record->subscription->current_period_start !== null)
            ->action(function (Restaurant $record): void {
                $subscription = $record->subscription;
                $start = $subscription?->current_period_start;

                if ($subscription === null || $start === null) {
                    return;
                }

                $invoice = app(IssueInvoice::class)->handle($subscription, $start, $subscription->current_period_end ?? $subscription->interval->addTo($start));

                Notification::make()->success()->title(__('clients.notifications.invoice_ready', ['number' => $invoice->number]))->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label(__('clients.actions.suspend'))
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Restaurant $record): string => __('clients.actions.suspend_heading', ['restaurant' => $record->name]))
            ->modalDescription(__('clients.actions.suspend_description'))
            ->schema([
                Textarea::make('reason')->label(__('clients.fields.suspension_reason'))->required()->minLength(5)->maxLength(300),
            ])
            ->visible(fn (Restaurant $record): bool => $record->isOperational() && Gate::allows('suspend', $record))
            ->action(function (Restaurant $record, array $data): void {
                /** @var User $admin */
                $admin = auth()->user();
                app(SuspendRestaurant::class)->handle($record, $data['reason'], $admin);

                Notification::make()->success()->title(__('clients.notifications.suspended'))->send();
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivate')
            ->label(__('clients.actions.reactivate'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (Restaurant $record): string => __('clients.actions.reactivate_heading', ['restaurant' => $record->name]))
            ->modalDescription(__('clients.actions.reactivate_description'))
            ->visible(fn (Restaurant $record): bool => ! $record->isOperational() && Gate::allows('suspend', $record))
            ->action(function (Restaurant $record): void {
                /** @var User $admin */
                $admin = auth()->user();
                app(ReactivateRestaurant::class)->handle($record, $admin);

                Notification::make()->success()->title(__('clients.notifications.reactivated'))->send();
            });
    }

    public static function resendInvitation(): Action
    {
        return Action::make('resendInvitation')
            ->label(__('clients.actions.resend_invitation'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('clients.actions.resend_invitation'))
            ->modalDescription(fn (Restaurant $record): string => __('clients.actions.resend_invitation_description', ['email' => (string) $record->owner()?->email]))
            ->visible(fn (Restaurant $record): bool => Gate::allows('update', $record)
                && ($owner = $record->owner()) !== null
                && $owner->last_login_at === null)
            ->action(function (Restaurant $record): void {
                $owner = $record->owner();

                if ($owner === null) {
                    return;
                }

                /** @var User $admin */
                $admin = auth()->user();
                $url = app(InviteUser::class)->handle($owner, $admin, context: $record->name);

                self::notifyInvitationLink($url, $owner->phone ?? $record->contact_phone, $record->name);
            });
    }

    /**
     * Persistent notification with the link and a one-tap WhatsApp share.
     */
    public static function notifyInvitationLink(string $url, ?string $phone, string $restaurantName): void
    {
        Notification::make()
            ->success()
            ->persistent()
            ->title(__('clients.notifications.invitation_ready'))
            ->body(__('clients.notifications.invitation_ready_body', ['url' => $url]))
            ->actions([
                Action::make('whatsapp')
                    ->label(__('clients.actions.share_whatsapp'))
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->button()
                    ->color('success')
                    ->url(WhatsApp::shareUrl($phone, __('clients.notifications.whatsapp_message', ['restaurant' => $restaurantName, 'url' => $url])), shouldOpenInNewTab: true),
            ])
            ->send();
    }
}
