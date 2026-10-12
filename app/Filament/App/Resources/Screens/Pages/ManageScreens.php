<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Screens\Pages;

use App\Domain\Branches\Actions\CreateDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Branches\QrCodes;
use App\Filament\App\Resources\Screens\DeviceResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class ManageScreens extends ManageRecords
{
    protected static string $resource = DeviceResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('devices.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addScreen')
                ->label(__('devices.actions.add'))
                ->icon('heroicon-m-plus')
                ->modalHeading(__('devices.create.heading'))
                ->modalDescription(__('devices.create.description'))
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel(__('devices.actions.get_code'))
                ->schema(DeviceResource::formSchema(creating: true))
                ->visible(fn (): bool => Gate::allows('create', Device::class))
                ->disabled(fn (): bool => Workspace::branchOptions() === [])
                ->tooltip(fn () => Workspace::branchOptions() === [] ? __('devices.needs_branch') : null)
                ->action(function (array $data): void {
                    $branch = Branch::query()->whereKey(array_keys(Workspace::branchOptions()))->whereKey($data['branch_id'])->firstOrFail();
                    $kind = $data['kind'] instanceof DeviceKind ? $data['kind'] : DeviceKind::from((string) $data['kind']);

                    $code = app(CreateDevice::class)->handle($branch, $kind, (string) $data['name'], isset($data['station_id']) ? (int) $data['station_id'] : null, Workspace::user());

                    $this->replaceMountedAction('pairingCode', ['device' => $code->device->id, 'code' => $code->code]);
                }),
        ];
    }

    /** Shows a freshly issued code with the steps to pair, until the owner closes it. */
    public function pairingCodeAction(): Action
    {
        return Action::make('pairingCode')
            ->modalHeading(__('devices.code.heading'))
            ->modalIcon('heroicon-o-qr-code')
            ->modalWidth(Width::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('devices.code.done'))
            ->modalContent(function (array $arguments): HtmlString {
                $device = Device::query()->with('branch')->find($arguments['device'] ?? null);
                $code = (string) ($arguments['code'] ?? '');

                return new HtmlString(view('filament.app.pairing-code', [
                    'device' => $device,
                    'code' => substr($code, 0, 3).' '.substr($code, 3),
                    'pairUrl' => route('screen.pair'),
                    'qr' => app(QrCodes::class)->svg(route('screen.pair')),
                ])->render());
            });
    }
}
