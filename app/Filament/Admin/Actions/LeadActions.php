<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Domain\Billing\Models\Plan;
use App\Domain\Growth\Actions\AddLeadNote;
use App\Domain\Growth\Actions\ConvertLead;
use App\Domain\Growth\Actions\UpdateLeadStatus;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use App\Filament\Admin\Resources\Restaurants\Schemas\OnboardingSteps;
use App\Models\User;
use App\Support\WhatsApp;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Sales pipeline actions, available from the leads table and the lead page.
 */
final class LeadActions
{
    public static function changeStatus(): Action
    {
        return Action::make('changeStatus')
            ->label(__('leads.actions.change_status'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->modalWidth('lg')
            ->fillForm(fn (Lead $record): array => ['status' => $record->status, 'follow_up_at' => $record->follow_up_at])
            ->schema([
                Select::make('status')
                    ->label(__('clients.fields.status'))
                    ->options(collect(LeadStatus::cases())->reject(fn (LeadStatus $s): bool => $s === LeadStatus::Won)
                        ->mapWithKeys(fn (LeadStatus $s): array => [$s->value => $s->getLabel()])->all())
                    ->required()
                    ->live()
                    ->native(false),
                Textarea::make('lost_reason')
                    ->label(__('leads.fields.lost_reason'))
                    ->visible(fn (Get $get): bool => $get('status') === LeadStatus::Lost->value || $get('status') === LeadStatus::Lost)
                    ->required(fn (Get $get): bool => $get('status') === LeadStatus::Lost->value || $get('status') === LeadStatus::Lost)
                    ->maxLength(300),
                DateTimePicker::make('follow_up_at')
                    ->label(__('leads.fields.follow_up_at'))
                    ->native(false)
                    ->seconds(false)
                    ->hidden(fn (Get $get): bool => $get('status') === LeadStatus::Lost->value || $get('status') === LeadStatus::Lost),
            ])
            ->visible(fn (Lead $record): bool => $record->status !== LeadStatus::Won && Gate::allows('update', $record))
            ->action(function (Lead $record, array $data, Action $action): void {
                /** @var User $actor */
                $actor = auth()->user();
                $status = $data['status'] instanceof LeadStatus ? $data['status'] : LeadStatus::from($data['status']);

                try {
                    app(UpdateLeadStatus::class)->handle($record, $status, $actor, $data['lost_reason'] ?? null);
                } catch (DomainException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();
                }

                $record->update(['follow_up_at' => $status === LeadStatus::Lost ? null : ($data['follow_up_at'] ?? null)]);

                Notification::make()->success()->title(__('leads.notifications.status_updated', ['status' => $status->getLabel()]))->send();
            });
    }

    public static function addNote(): Action
    {
        return Action::make('addNote')
            ->label(__('leads.actions.add_note'))
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->modalWidth('lg')
            ->schema([
                Textarea::make('body')->label(__('leads.fields.note'))->required()->rows(4)->autosize()->maxLength(2000),
            ])
            ->visible(fn (Lead $record): bool => Gate::allows('update', $record))
            ->action(function (Lead $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(AddLeadNote::class)->handle($record, $data['body'], $actor);

                Notification::make()->success()->title(__('leads.notifications.note_added'))->send();
            });
    }

    public static function assignToMe(): Action
    {
        return Action::make('assignToMe')
            ->label(__('leads.actions.assign_to_me'))
            ->icon(Heroicon::OutlinedHandRaised)
            ->visible(fn (Lead $record): bool => $record->status->isOpen() && $record->assigned_to !== auth()->id() && Gate::allows('update', $record))
            ->action(function (Lead $record): void {
                $record->update(['assigned_to' => auth()->id()]);

                Notification::make()->success()->title(__('leads.notifications.assigned'))->send();
            });
    }

    public static function whatsapp(): Action
    {
        return Action::make('whatsapp')
            ->label(__('leads.actions.whatsapp'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (Lead $record): bool => filled($record->phone))
            ->url(fn (Lead $record): string => WhatsApp::shareUrl($record->phone, __('leads.whatsapp_greeting', ['name' => $record->contact_name], $record->locale)), shouldOpenInNewTab: true);
    }

    public static function convert(): Action
    {
        return Action::make('convert')
            ->label(__('leads.actions.convert'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->modalHeading(fn (Lead $record): string => __('leads.actions.convert_heading', ['restaurant' => $record->restaurant_name]))
            ->modalDescription(__('leads.actions.convert_description'))
            ->modalIcon(Heroicon::OutlinedSparkles)
            ->modalSubmitActionLabel(__('leads.actions.convert_submit'))
            ->modalWidth('4xl')
            ->fillForm(function (Lead $record): array {
                $plan = $record->plan ?? Plan::query()->available()->where('is_highlighted', true)->first() ?? Plan::query()->available()->first();

                return [
                    'name' => ['en' => $record->restaurant_name, 'ar' => $record->restaurant_name],
                    'slug' => Str::slug($record->restaurant_name),
                    'contact_phone' => $record->phone,
                    'default_locale' => $record->locale,
                    'timezone' => 'Asia/Qatar',
                    'owner_name' => $record->contact_name,
                    'owner_email' => $record->email,
                    'owner_phone' => $record->phone,
                    'send_invitation' => true,
                    'plan_id' => $plan?->id,
                    'branches' => $record->branches_count,
                    'trial_days' => $plan->trial_days ?? 14,
                    'negotiated_price' => $plan?->isCustomPriced() ?? false,
                ];
            })
            ->schema([
                Section::make(__('clients.wizard.steps.brand'))->schema(OnboardingSteps::brandFields()),
                Section::make(__('clients.wizard.steps.owner'))->schema(OnboardingSteps::ownerFields()),
                Section::make(__('clients.wizard.steps.plan'))->schema(OnboardingSteps::planFields()),
            ])
            ->visible(fn (Lead $record): bool => Gate::allows('convert', $record))
            ->action(function (Lead $record, array $data, Action $action): void {
                /** @var User $actor */
                $actor = auth()->user();
                $result = null;

                try {
                    $result = app(ConvertLead::class)->handle($record, OnboardingSteps::toData($data), $actor);
                } catch (DomainException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();
                }

                if ($result === null) {
                    return;
                }

                if ($result['invitation_url'] !== null) {
                    session()->flash('client_invitation_url', $result['invitation_url']);
                }

                Notification::make()->success()->title(__('leads.notifications.converted', ['restaurant' => $result['restaurant']->name]))->send();

                $action->redirect(RestaurantResource::getUrl('view', ['record' => $result['restaurant']]));
            });
    }
}
