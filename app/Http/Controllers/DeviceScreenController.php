<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Branches\Actions\PairDevice;
use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Device;
use App\Domain\Ordering\KitchenLoad;
use App\Http\Middleware\AuthenticateDevice;
use App\Support\Digits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Shared branch screens: pair with a one-time code, then stay signed in on a
 * standby view that keeps the display awake and reports its heartbeat.
 */
final class DeviceScreenController
{
    /** Paired screens stay signed in for about five years, or until revoked. */
    private const COOKIE_MINUTES = 60 * 24 * 365 * 5;

    public function pairForm(Request $request): View|RedirectResponse
    {
        if (AuthenticateDevice::resolve($request) !== null) {
            return redirect()->route('screen.show');
        }

        return view('screen.pair', ['status' => session('screen_status')]);
    }

    public function pair(Request $request, PairDevice $pair): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $result = $pair->handle(Digits::toLatin((string) $request->string('code')), $request->ip(), $request->userAgent());

        if ($result === null) {
            throw ValidationException::withMessages(['code' => __('devices.pair.invalid')]);
        }

        return redirect()->route('screen.show')->withCookie(cookie(
            AuthenticateDevice::COOKIE,
            $result['token'],
            self::COOKIE_MINUTES,
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /** The paired screen's live view: kitchen, cashier board, pickup window or TV. */
    public function show(Request $request): View
    {
        $device = $this->device($request);

        $screen = match ($device->kind) {
            DeviceKind::Kitchen => 'ops.kitchen-display',
            DeviceKind::Cashier => 'ops.live-board',
            DeviceKind::Pickup => 'ops.pickup-window',
            DeviceKind::Tv => 'ops.command-center',
            DeviceKind::Pager => 'ops.pager-counter',
            DeviceKind::ReadyBoard => 'ops.ready-board',
        };

        abort_unless($device->branch->restaurant->hasService(OpsController::serviceFor($device->kind)), 403, __('pager.service_off_screen'));

        return view('ops.page', [
            'title' => $device->branch->name.' · '.$device->kind->getLabel(),
            'screen' => $screen,
            'params' => ['branchId' => $device->branch_id, 'deviceId' => $device->id],
        ]);
    }

    public function heartbeat(Request $request, BranchSchedule $schedule): JsonResponse
    {
        return response()->json($this->status($this->device($request), $schedule));
    }

    /** Forget the pairing on this screen only; the owner can still re-pair it. */
    public function disconnect(Request $request): RedirectResponse
    {
        return redirect()->route('screen.pair')->withCookie(cookie()->forget(AuthenticateDevice::COOKIE));
    }

    /**
     * @return array{paired: true, state: string, label: string, tone: string, prep: int, operational: bool, opens_at: ?string, closes_at: ?string}
     */
    private function status(Device $device, BranchSchedule $schedule): array
    {
        $branch = $device->branch;
        $state = $schedule->state($branch);

        return [
            'paired' => true,
            'state' => $state->value,
            'label' => $state->getLabel(),
            'tone' => $state->getColor(),
            'prep' => app(KitchenLoad::class)->estimate($branch),
            'operational' => $branch->restaurant->isOperational(),
            'opens_at' => $state->acceptsOrders() ? null : $schedule->reopensAt($branch)?->format('H:i'),
            'closes_at' => $state->acceptsOrders() ? $schedule->closesAt($branch)?->format('H:i') : null,
        ];
    }

    private function device(Request $request): Device
    {
        $device = $request->attributes->get('device');

        abort_unless($device instanceof Device, 401);

        return $device;
    }
}
