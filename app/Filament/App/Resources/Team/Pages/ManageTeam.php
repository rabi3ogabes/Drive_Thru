<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Team\Pages;

use App\Domain\Billing\Exceptions\PlanLimitReached;
use App\Domain\Billing\PlanLimits;
use App\Domain\Staff\Actions\InviteEmployee;
use App\Domain\Staff\Data\EmployeeData;
use App\Domain\Staff\Models\Employee;
use App\Filament\Admin\Actions\ClientActions;
use App\Filament\App\Resources\Team\EmployeeResource;
use App\Filament\App\Resources\Team\Schemas\EmployeeForm;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageTeam extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $limits = app(PlanLimits::class);
        $restaurant = Workspace::restaurant();
        $limit = $limits->limit($restaurant, PlanLimits::EMPLOYEES);

        return $limit === null
            ? __('staff.subheading')
            : __('billing.limits.employees.usage', ['used' => $limits->used($restaurant, PlanLimits::EMPLOYEES), 'limit' => $limit]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('staff.actions.invite'))
                ->icon('heroicon-m-user-plus')
                ->modalHeading(__('staff.invite.heading'))
                ->modalDescription(__('staff.invite.description'))
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel(__('staff.actions.send_invite'))
                ->schema(EmployeeForm::schema())
                ->visible(fn (): bool => Gate::allows('create', Employee::class))
                ->action(function (array $data, Action $action): void {
                    try {
                        $result = app(InviteEmployee::class)->handle(Workspace::restaurant(), EmployeeData::fromArray($data), Workspace::user());
                    } catch (PlanLimitReached $e) {
                        Notification::make()->warning()->title(__('staff.limit.heading'))->body($e->getMessage().' '.__('branches.limit.contact'))->persistent()->send();
                        throw new Halt;
                    } catch (AuthorizationException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        throw new Halt;
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                        throw new Halt;
                    }

                    ClientActions::notifyInvitationLink($result['url'], $result['employee']->user->phone, Workspace::restaurant()->name);
                }),
        ];
    }
}
